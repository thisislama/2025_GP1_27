import os
import pandas as pd
import xml.etree.ElementTree as ET
import matplotlib.pyplot as plt
from PIL import Image
import matplotlib.patches as patches

def create_clean_dataset(images_dir, annotations_dir):
    """Create and clean your ventilator dataset"""
    
    # 1. Create DataFrame from files
    data = []
    for img_file in os.listdir(images_dir):
        if img_file.lower().endswith(('.png', '.jpg', '.jpeg')):
            img_path = os.path.join(images_dir, img_file)
            xml_file = img_file.rsplit('.', 1)[0] + '.xml'
            xml_path = os.path.join(annotations_dir, xml_file)
            
            data.append({
                'image_path': img_path,
                'xml_path': xml_path,
                'image_name': img_file,
                'has_xml': os.path.exists(xml_path)
            })
    
    df = pd.DataFrame(data)
    print(f"Found {len(df)} total images")
    
    # 2. Keep only images with annotations
    df_clean = df[df['has_xml']].copy()
    print(f"After cleaning: {len(df_clean)} images with annotations")
    
    return df_clean

# Usage
images_dir = "C:/MAMP/htdocs/2025_GP_27/model1/newDataset_images"
annotations_dir = "C:/MAMP/htdocs/2025_GP_27/model1/annotations"

df = create_clean_dataset(images_dir, annotations_dir)
df.to_pickle('clean_ventilator_data.pkl')
print("Done! Your dataset is ready for training.")