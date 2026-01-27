from fastapi import FastAPI, File, UploadFile
from torchvision import models, transforms
from PIL import Image
from io import BytesIO
import torch
import torch.nn as nn

app = FastAPI(title="Image Event Detection API")

num_classes = 12

# Load model EXACTLY as trained
model = models.resnet18(weights=None)
model.fc = nn.Linear(model.fc.in_features, num_classes)

model.load_state_dict(
    torch.load("best_resnet18_2.pth", map_location="cpu")
)
model.eval()

transform = transforms.Compose([
    transforms.Resize((224, 224)),
    transforms.ToTensor(),
])

@app.get("/")
def root():
    return {"message": "API running"}

@app.post("/predict")
async def predict(file: UploadFile = File(...)):
    image = Image.open(BytesIO(await file.read())).convert("RGB")
    x = transform(image).unsqueeze(0)

    with torch.no_grad():
        outputs = model(x)
        pred = torch.argmax(outputs, dim=1).item()

    return {
        "class_id": pred
    }
