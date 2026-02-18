from fastapi import FastAPI, UploadFile, File
from fastapi.middleware.cors import CORSMiddleware

app = FastAPI()

# Add this to allow your PHP dashboard to talk to Python
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"], # In production, replace "*" with your domain
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.post("/predict")
async def predict(file: UploadFile = File(...)):
    # Your AI processing logic here
    # Example return:
    return {"predicted_class": "Anomaly", }