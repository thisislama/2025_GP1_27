from fastapi import FastAPI
from pydantic import BaseModel
from typing import List
import torch

from model.model_def import EventDetector

app = FastAPI(title="Event Detection API")

# ---- Load model ONCE ----
input_size = 224        # must match training
num_classes = 12         # number of events

model = EventDetector(input_size, num_classes)
model.load_state_dict(
    torch.load("/best_resnet18_2.pth", map_location="cpu")
)
model.eval()

# ---- Input schema ----
class EventInput(BaseModel):
    features: List[float]

@app.get("/")
def root():
    return {"message": "API running"}

@app.post("/predict")
def predict_event(data: EventInput):
    x = torch.tensor([data.features], dtype=torch.float32)

    with torch.no_grad():
        outputs = model(x)
        predicted_class = torch.argmax(outputs, dim=1).item()

    return {
        "detected_event": predicted_class
    }
