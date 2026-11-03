#!/usr/bin/env python3
"""Décodage réel de QR codes pour tests/totp-test.php (non déployé).

Usage :
    QR_DECODER_PATH=/tmp/claude-0/qrtest python3 -I tests/qr-decode.py image1.png [image2.png ...]

Les décodeurs s'installent dans un dossier dédié, sans toucher au système :
    pip install --target /tmp/claude-0/qrtest zxing-cpp opencv-python-headless

Sortie (JSON sur stdout) : pour chaque image, le contenu décodé par chaque
décodeur disponible, en hexadécimal (octets exacts, sans interprétation de
l'encodage) ; null si le décodeur n'a rien lu.
    {"decoders": ["zxing-cpp", "opencv"],
     "results": [{"file": "...", "zxing-cpp": "6f74...", "zxing-ec": "M", "opencv": "6f74..."}]}
Code de sortie 2 si aucun décodeur n'est disponible.
"""

import json
import os
import sys

extra = os.environ.get("QR_DECODER_PATH")
if extra:
    sys.path.insert(0, extra)

try:
    import cv2  # sert aussi à lire les PNG
except ImportError:
    cv2 = None

try:
    import zxingcpp
except ImportError:
    zxingcpp = None


def main(paths):
    if cv2 is None:
        print(json.dumps({"error": "opencv (cv2) introuvable : pip install opencv-python-headless"}))
        return 2

    decoders = []
    if zxingcpp is not None:
        decoders.append("zxing-cpp")
    decoders.append("opencv")

    # Deux détecteurs OpenCV : le classique échoue parfois sur la localisation
    # (environ 1 image sur 10, y compris avec des QR de référence) ; la variante
    # Aruco sert alors de repli. Le décodage reste celui d'OpenCV.
    detectors = [cv2.QRCodeDetector()]
    if hasattr(cv2, "QRCodeDetectorAruco"):
        detectors.append(cv2.QRCodeDetectorAruco())
    results = []
    for path in paths:
        image = cv2.imread(path, cv2.IMREAD_GRAYSCALE)
        entry = {"file": path}
        if image is None:
            entry["error"] = "image illisible"
            results.append(entry)
            continue

        if zxingcpp is not None:
            found = zxingcpp.read_barcodes(image, formats=zxingcpp.BarcodeFormat.QRCode)
            entry["zxing-cpp"] = found[0].bytes.hex() if found else None
            entry["zxing-ec"] = found[0].ec_level if found else None

        entry["opencv"] = None
        for detector in detectors:
            text, _points, _straight = detector.detectAndDecode(image)
            if text:
                entry["opencv"] = text.encode("utf-8").hex()
                break
        results.append(entry)

    print(json.dumps({"decoders": decoders, "results": results}))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
