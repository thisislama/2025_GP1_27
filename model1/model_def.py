import torch.nn as nn
from torchvision import models

class EventDetector(nn.Module):
    def __init__(self, num_classes=9):
        super().__init__()

        self.conv1 = None  # placeholder (see below)
        self.model = models.resnet18(weights=None)

        self.model.fc = nn.Linear(
            self.model.fc.in_features,
            num_classes
        )

    def forward(self, x):
        return self.model(x)
