# Navigation Icons Setup Guide

## Current Status

The sidebar is currently using emoji fallbacks (🏠, 👥, 📞, etc.) for navigation icons.

## How to Add Custom PNG Icons

### Step 1: Create/Download Icons

Create or download 32x32px or 48x48px PNG icons with the following names:

1. `dashboard.png` - Home/Dashboard icon
2. `employees.png` - Team/People icon
3. `crm.png` - Phone/Contacts icon
4. `expenses.png` - Money/Wallet icon
5. `documents.png` - Folder/Files icon
6. `visitors.png` - Clipboard/Log icon
7. `analytics.png` - Chart/Graph icon
8. `settings.png` - Gear/Cog icon
9. `roles.png` - Shield/Lock icon
10. `branding.png` - Palette/Brush icon
11. `notifications.png` - Bell icon
12. `logout.png` - Exit/Door icon

### Step 2: Icon Design Guidelines

**Format**: PNG with transparent background
**Size**: 32x32px or 48x48px (will auto-scale)
**Color**: White or light color (displays on dark blue #003581)
**Style**: Consistent flat design, line or filled

### Step 3: Place Files

Save all PNG files in:
```
assets/icons/
```

### Step 4: Automatic Detection

The system automatically detects PNG files and uses them. If a PNG is not found, it falls back to emoji.

## Quick Icon Sources

### Free Icon Libraries:

1. **Font Awesome** (https://fontawesome.com)
   - Search for icons
   - Download as PNG
   - Free version available

2. **Flaticon** (https://www.flaticon.com)
   - Thousands of free icons
   - Download as PNG
   - Attribute if required

3. **Icons8** (https://icons8.com)
   - Free for personal use
   - Various styles available

4. **Feather Icons** (https://feathericons.com)
   - Simple, clean line icons
   - Open source

### Create Your Own:

1. **Figma** (free) - Design custom icons
2. **Adobe Illustrator** - Professional icon design
3. **Canva** (free) - Simple icon creation
4. **Inkscape** (free) - Open source vector editor

## Export Settings

When exporting icons:
- Format: PNG
- Size: 32x32px or 48x48px
- Background: Transparent
- Color: White (#FFFFFF) or light color

## Icon Behavior

**Expanded Sidebar**:
- Icon + Text label displayed
- Icons are white on blue background
- Hover: Orange background, original icon color

**Collapsed Sidebar**:
- Only icon visible
- Tooltip shows label on hover
- Centered in sidebar

**Active State**:
- Orange background (#faa718)
- Icon maintains visibility

## Testing

After adding icons:
1. Refresh the dashboard page
2. Icons should appear automatically
3. Test collapsed and expanded states
4. Verify all icons are visible

## Troubleshooting

**Icon not showing?**
- Check file name matches exactly (case-sensitive)
- Ensure PNG format
- Verify file is in `assets/icons/` folder
- Check file permissions

**Icon too dark/light?**
- Use white or very light color for icons
- Test on dark blue background
- Adjust in image editor if needed

**Icon looks pixelated?**
- Use larger size (48x48px minimum)
- Export at 2x resolution for retina displays
- Use SVG if possible (requires code modification)
