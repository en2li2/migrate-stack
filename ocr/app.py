import os
import tempfile
from fastapi import FastAPI, File, UploadFile

app = FastAPI()

_ocr = None


def get_ocr():
    global _ocr
    if _ocr is None:
        from paddleocr import PaddleOCR
        lang = os.environ.get("OCR_LANG", "latin")
        # PaddleOCR 3.x: belge yönü + satır yönü sınıflandırıcı açık; kart
        # fotoğrafındaki küçük/dönük metni de yakalar (Tesseract'ın aksine
        # tespit tabanlı, tüm-görüntü OCR değil).
        try:
            _ocr = PaddleOCR(
                lang=lang,
                use_doc_orientation_classify=True,
                use_doc_unwarping=False,
                use_textline_orientation=True,
            )
        except TypeError:
            _ocr = PaddleOCR(use_angle_cls=True, lang=lang)
    return _ocr


def run_ocr(path):
    o = get_ocr()
    texts = []

    # PaddleOCR 3.x: predict() -> list[OCRResult(dict benzeri)]
    if hasattr(o, "predict"):
        try:
            for res in o.predict(path):
                rt = None
                try:
                    rt = res["rec_texts"]
                except Exception:
                    rt = getattr(res, "rec_texts", None)
                if rt:
                    texts.extend([str(t) for t in rt])
            if texts:
                return texts
        except Exception:
            texts = []

    # 2.x fallback: ocr(path, cls=True)
    try:
        res = o.ocr(path, cls=True)
        for page in (res or []):
            for item in (page or []):
                try:
                    texts.append(str(item[1][0]))
                except Exception:
                    pass
    except Exception:
        pass

    return texts


@app.get("/health")
def health():
    try:
        get_ocr()
        return {"ok": True}
    except Exception as e:
        return {"ok": False, "error": str(e)}


@app.post("/ocr")
async def ocr_endpoint(file: UploadFile = File(...)):
    data = await file.read()
    suffix = os.path.splitext(file.filename or "")[1] or ".png"
    with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as tmp:
        tmp.write(data)
        path = tmp.name
    try:
        lines = run_ocr(path)
    finally:
        try:
            os.unlink(path)
        except OSError:
            pass
    return {"lines": lines, "text": "\n".join(lines)}
