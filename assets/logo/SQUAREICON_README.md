# Square Icon Setup Guide

## Purpose
The `squareicon.png` is displayed in the sidebar header when the sidebar is in collapsed state. It replaces the full logo and serves as a clickable button to expand the sidebar.

## File Requirements

### Filename
- **Required filename:** `squareicon.png`
- **Location:** `assets/logo/squareicon.png`

### Image Specifications
- **Format:** PNG with transparency
- **Size:** 40x40 pixels (recommended) or 48x48 pixels
- **Background:** Transparent or white
- **Colors:** Should use brand colors (#003581 for primary, #faa718 for accent)
- **Style:** Simple, recognizable icon or monogram

### Design Recommendations
1. **Simple Design:** Use a simple icon, monogram, or symbol
2. **High Contrast:** Ensure visibility on dark blue (#003581) background
3. **Square Shape:** Keep design within a square boundary
4. **Crisp Edges:** Use vector graphics or high-resolution images

## Example Designs
- Company monogram (e.g., "K" for Karyalay)
- Simplified logo icon
- Menu/hamburger icon
- Grid or dashboard icon

## Usage
The square icon is:
- **Shown:** When sidebar is collapsed (70px width)
- **Hidden:** When sidebar is expanded (260px width)
- **Clickable:** Clicking it expands the sidebar

## Fallback
If `squareicon.png` is not found, the system displays:
- Orange square with white "K" text
- Same functionality as custom icon

## Creating Your Icon

### Option 1: Using Design Software
1. Create a 40x40px canvas in Photoshop/Illustrator/Figma
2. Design your icon using brand colors
3. Export as PNG with transparency
4. Save as `squareicon.png` in `assets/logo/` folder

### Option 2: Using Online Tools
1. Visit: https://www.canva.com or https://www.photopea.com
2. Create 40x40px design
3. Use brand colors (#003581, #faa718)
4. Download as PNG
5. Rename to `squareicon.png`
6. Upload to `assets/logo/` folder

## Testing
1. Place `squareicon.png` in `assets/logo/` folder
2. Visit `http://localhost/KaryalayERP/public/index.php`
3. Click hamburger menu (☰) to collapse sidebar
4. Verify your icon appears in the header
5. Click the icon to verify sidebar expands

## Current Status
- ✅ System configured to use `squareicon.png`
- ✅ Fallback display working (orange "K" square)
- ⚠️ Custom `squareicon.png` file needs to be added

---
**Last Updated:** October 24, 2025
