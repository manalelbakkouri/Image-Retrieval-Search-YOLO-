import numpy as np
import cv2
from sklearn.cluster import KMeans
from skimage.filters import gabor
from skimage.feature import local_binary_pattern
from scipy import ndimage as ndi

from utils.cv_ops import l2_normalize
from sklearn.cluster import MiniBatchKMeans


class FeatureService:
    """
    Extrait des descripteurs FIXES + construit un vecteur final (dim constante).
    Vecteur final = concat blocs normalisés, puis normalisation globale.
    """

    # --- Paramètres descripteurs (fixes) ---
    HSV_H_BINS = 16
    HSV_S_BINS = 4
    HSV_V_BINS = 4

    DOM_COLORS_K = 3  # 3 couleurs dominantes (pour le vecteur)

    #  AJOUT: nombre de couleurs dominantes "visuelles" (UI)
    DOM_COLORS_VIS_K = 5
    DOM_COLORS_SAMPLE = 8000  # sample pixels pour accélérer

    GABOR_FREQS = [0.1, 0.2, 0.3]
    GABOR_THETAS = [0, np.pi/6, 2*np.pi/6, 3*np.pi/6, 4*np.pi/6, 5*np.pi/6]  # 6 orientations

    ORIENT_BINS = 36  # histogramme orientations du contour
    LBP_P = 8
    LBP_R = 1
    LBP_METHOD = "uniform"  # hist taille fixe

    def describe_object(self, crop_bgr):
        if crop_bgr is None or crop_bgr.size == 0:
            raise ValueError("Empty crop")

        # Resize pour stabilité (évite vecteurs instables sur tailles extrêmes)
        crop_bgr = self._safe_resize(crop_bgr, max_side=256)

        # Descripteurs
        color_hist = self._color_hist_hsv(crop_bgr)

        # --- dominant colors ---
        # (1) garde ton vecteur LAB (pour index/FAISS)
        dom_colors = self._dominant_colors_lab(crop_bgr, k=self.DOM_COLORS_K)

        #  AJOUT (2) couleurs dominantes VISUELLES en RGB + ratios (pour UI)
        dom_rgb, dom_ratio = self._dominant_colors_rgb(crop_bgr, k=self.DOM_COLORS_VIS_K)

        gabor_vec = self._gabor_stats(crop_bgr)
        tamura = self._tamura_simple(crop_bgr)
        hu = self._hu_moments(crop_bgr)
        orient_hist = self._contour_orientation_hist(crop_bgr, bins=self.ORIENT_BINS)

        # Méthode supplémentaire (choix robuste) : LBP histogram
        lbp_hist = self._lbp_hist(crop_bgr)

        # --- Fusion (concat) + normalisation par bloc ---
        # Chaque bloc est L2-normalisé pour éviter domination d’un descripteur
        blocks = [
            l2_normalize(color_hist),
            l2_normalize(dom_colors),
            l2_normalize(gabor_vec),
            l2_normalize(tamura),
            l2_normalize(hu),
            l2_normalize(orient_hist),
            l2_normalize(lbp_hist),
        ]
        feature_vector = np.concatenate(blocks).astype(np.float32)
        feature_vector = l2_normalize(feature_vector).astype(np.float32)

        # JSON friendly
        return {
            "color_hist_hsv": color_hist.tolist(),

            #  garde l'ancien (vecteur LAB + weights)
            "dominant_colors_lab": dom_colors.tolist(),

            #  AJOUT pour UI: vraies couleurs visibles
            "dominant_colors_rgb": dom_rgb,            # [[r,g,b],...]
            "dominant_colors_ratio": dom_ratio,        # [0.42, 0.31, ...]

            "gabor": gabor_vec.tolist(),
            "tamura": tamura.tolist(),
            "hu_moments": hu.tolist(),
            "orientation_hist": orient_hist.tolist(),
            "lbp_hist": lbp_hist.tolist(),
            "feature_vector": feature_vector.tolist(),
            "feature_dim": int(feature_vector.shape[0]),
        }

    # ----------------- Helpers -----------------

    def _safe_resize(self, img, max_side=256):
        h, w = img.shape[:2]
        m = max(h, w)
        if m <= max_side:
            return img
        scale = max_side / m
        new_w, new_h = int(round(w * scale)), int(round(h * scale))
        return cv2.resize(img, (new_w, new_h), interpolation=cv2.INTER_AREA)

    def _color_hist_hsv(self, bgr):
        hsv = cv2.cvtColor(bgr, cv2.COLOR_BGR2HSV)
        h_bins, s_bins, v_bins = self.HSV_H_BINS, self.HSV_S_BINS, self.HSV_V_BINS
        hist = cv2.calcHist([hsv], [0, 1, 2], None,
                            [h_bins, s_bins, v_bins],
                            [0, 180, 0, 256, 0, 256])
        hist = hist.flatten().astype(np.float32)
        hist_sum = float(hist.sum())
        if hist_sum > 0:
            hist /= hist_sum
        return hist

    def _dominant_colors_lab(self, bgr, k=3):
        lab = cv2.cvtColor(bgr, cv2.COLOR_BGR2LAB)
        pixels = lab.reshape(-1, 3).astype(np.float32)

        # sous-échantillonnage si image grande
        if pixels.shape[0] > 20000:
            idx = np.random.choice(pixels.shape[0], 20000, replace=False)
            pixels = pixels[idx]

        km = KMeans(n_clusters=k, n_init=5, random_state=0)
        labels = km.fit_predict(pixels)
        centers = km.cluster_centers_  # (k,3)
        counts = np.bincount(labels, minlength=k).astype(np.float32)
        weights = counts / max(1.0, float(counts.sum()))  # proportions

        # vecteur fixe: [c1L,c1a,c1b, c2..., c3..., w1,w2,w3]
        vec = np.concatenate([centers.flatten(), weights]).astype(np.float32)
        return vec

    #  AJOUT: Dominant colors pour affichage UI (RGB)
    def _dominant_colors_rgb(self, bgr, k=5):
        """
        Dominant colors robustes:
        - convertit en HSV
        - filtre pixels peu saturés (gris/blanc/noir)
        - KMeans sur RGB filtré
        """
        if bgr is None or bgr.size == 0:
            return [], []

        # Resize safe (déjà fait avant)
        hsv = cv2.cvtColor(bgr, cv2.COLOR_BGR2HSV)
        rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB)

        pixels_hsv = hsv.reshape(-1, 3).astype(np.float32)
        pixels_rgb = rgb.reshape(-1, 3).astype(np.float32)

        # Sample pour performance
        n = pixels_rgb.shape[0]
        if n > self.DOM_COLORS_SAMPLE:
            idx = np.random.choice(n, self.DOM_COLORS_SAMPLE, replace=False)
            pixels_hsv = pixels_hsv[idx]
            pixels_rgb = pixels_rgb[idx]

        # ✅ filtre saturation (enlève gris/blanc)
        S = pixels_hsv[:, 1]
        V = pixels_hsv[:, 2]

        # garder pixels "colorés" : saturation > 35 et luminance raisonnable
        mask = (S > 35) & (V > 20) & (V < 245)

        rgb_f = pixels_rgb[mask]

        # fallback si trop peu de pixels colorés
        if rgb_f.shape[0] < 80:
            mean = pixels_rgb.mean(axis=1)
            mask2 = (mean > 15) & (mean < 245)
            rgb_f = pixels_rgb[mask2]
            if rgb_f.shape[0] < 80:
                rgb_f = pixels_rgb

        km = MiniBatchKMeans(n_clusters=k, random_state=0, batch_size=2048, n_init="auto")
        labels = km.fit_predict(rgb_f)
        centers = km.cluster_centers_

        counts = np.bincount(labels, minlength=k).astype(np.float32)
        total = float(counts.sum()) if counts.sum() > 0 else 1.0
        ratios = counts / total

        order = np.argsort(-ratios)
        centers = centers[order].astype(np.int32)
        centers = np.clip(centers, 0, 255)
        ratios = ratios[order]

        return centers.tolist(), ratios.astype(float).tolist()


    def _gabor_stats(self, bgr):
        gray = cv2.cvtColor(bgr, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
        feats = []
        for f in self.GABOR_FREQS:
            for theta in self.GABOR_THETAS:
                real, imag = gabor(gray, frequency=f, theta=theta)
                # stats robustes
                feats.append(float(real.mean()))
                feats.append(float(real.var()))
        return np.array(feats, dtype=np.float32)

    def _tamura_simple(self, bgr):
        """
        Version simple & stable (académique) :
        - Coarseness approx via énergie gradients à plusieurs échelles
        - Contrast via std / kurtosis-like
        - Directionality via histogramme d'angles de gradient
        """
        gray = cv2.cvtColor(bgr, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0

        # gradients
        gx = cv2.Sobel(gray, cv2.CV_32F, 1, 0, ksize=3)
        gy = cv2.Sobel(gray, cv2.CV_32F, 0, 1, ksize=3)
        mag = np.sqrt(gx * gx + gy * gy) + 1e-12
        ang = (np.arctan2(gy, gx) + np.pi)  # [0,2pi]

        energies = []
        for sigma in (1.0, 2.0, 4.0):
            sm = ndi.gaussian_filter(mag, sigma=sigma)
            energies.append(float(sm.mean()))
        coarseness = float(np.mean(energies))

        contrast = float(gray.std())

        bins = 36
        hist, _ = np.histogram(ang.flatten(), bins=bins, range=(0, 2*np.pi), weights=mag.flatten(), density=False)
        hist = hist.astype(np.float32)
        s = float(hist.sum())
        if s > 0:
            hist /= s
        entropy = float(-(hist * np.log(hist + 1e-12)).sum())
        directionality = float(1.0 / (entropy + 1e-6))

        return np.array([coarseness, contrast, directionality], dtype=np.float32)

    def _hu_moments(self, bgr):
        gray = cv2.cvtColor(bgr, cv2.COLOR_BGR2GRAY)
        thr = cv2.adaptiveThreshold(gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
                                    cv2.THRESH_BINARY, 31, 2)
        contours, _ = cv2.findContours(thr, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        if not contours:
            return np.zeros(7, dtype=np.float32)

        cnt = max(contours, key=cv2.contourArea)
        m = cv2.moments(cnt)
        hu = cv2.HuMoments(m).flatten().astype(np.float32)
        hu = np.sign(hu) * np.log10(np.abs(hu) + 1e-12)
        return hu

    def _contour_orientation_hist(self, bgr, bins=36):
        gray = cv2.cvtColor(bgr, cv2.COLOR_BGR2GRAY)
        edges = cv2.Canny(gray, 80, 160)

        gx = cv2.Sobel(edges.astype(np.float32), cv2.CV_32F, 1, 0, ksize=3)
        gy = cv2.Sobel(edges.astype(np.float32), cv2.CV_32F, 0, 1, ksize=3)
        mag = np.sqrt(gx*gx + gy*gy) + 1e-12
        ang = (np.arctan2(gy, gx) + np.pi)  # [0,2pi]

        hist, _ = np.histogram(ang.flatten(), bins=bins, range=(0, 2*np.pi), weights=mag.flatten(), density=False)
        hist = hist.astype(np.float32)
        s = float(hist.sum())
        if s > 0:
            hist /= s
        return hist

    def _lbp_hist(self, bgr):
        gray = cv2.cvtColor(bgr, cv2.COLOR_BGR2GRAY)
        lbp = local_binary_pattern(gray, P=self.LBP_P, R=self.LBP_R, method=self.LBP_METHOD)

        n_bins = self.LBP_P + 2
        hist, _ = np.histogram(lbp.ravel(), bins=n_bins, range=(0, n_bins), density=False)
        hist = hist.astype(np.float32)
        s = float(hist.sum())
        if s > 0:
            hist /= s
        return hist
