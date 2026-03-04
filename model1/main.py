from fastapi import FastAPI, File, UploadFile
from torchvision import models, transforms
from PIL import Image
from io import BytesIO
import torch
import torch.nn as nn
import numpy as np
from denoise_process import DenoiseTransform
from fastapi import HTTPException

app = FastAPI(title="Image Event Detection API")

class_names = [
   "Accumulation Flow",
   "Accumulation Volume",
   "Double_Triggering Flow",
   "Double_Triggering Volume",
   "Ineffective_effort Flow",
   "Ineffective_effort Volume",
   "Leakage Flow",
   "Leakage Volume",
   "Normal Flow",
   "Normal Volume",
   "Premature_cycling Flow",
   "Premature_cycling Volume"
]

num_classes = 12

# Load model EXACTLY as trained
model = models.resnet18(weights=None)
model.fc = nn.Linear(model.fc.in_features, num_classes)

model.load_state_dict(
    torch.load("best_resnet18_2.pth", map_location="cpu")
)
model.eval()
# new step :
class RoughWaveformCrop:
    def __call__(self, img):
        w, h = img.size

        # قص تقريبي لمنطقة waveform
        left   = int(w * 0.05)
        right  = int(w * 0.95)

        top    = int(h * 0.25)
        bottom = int(h * 0.6)

        return img.crop((left, top, right, bottom))

transform = transforms.Compose([
     RoughWaveformCrop(),          #NEW
    transforms.Resize((224, 224)),
    DenoiseTransform(),
    transforms.ToTensor(),
    transforms.Normalize(
        mean=[0.485, 0.456, 0.406],
        std =[0.229, 0.224, 0.225]
    )
])

@app.get("/")
def root():
    return {"message": "API running"}

@app.post("/predict")
async def predict(file: UploadFile = File(...)):
    # Step 1: read image safely
    try:
        image = Image.open(file.file).convert("RGB")
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Invalid image: {e}")

    # Step 2: apply transform
    try:
        input_tensor = transform(image).unsqueeze(0)  # [1,3,224,224]
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Transform error: {e}")

    # Step 3: inference
    try:
        with torch.no_grad():
            outputs = model(input_tensor)
            _, predicted = torch.max(outputs, 1)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Inference error: {e}")

    # Step 4: return class
    return {"predicted_class": class_names[predicted.item()]}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)