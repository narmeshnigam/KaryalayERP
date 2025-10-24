# Karyalay ERP - Brand Colors Reference

## Official Brand Colors (From Logo)

### Primary Colors
- **Primary Blue**: `#003581`
  - Used for: Headers, primary buttons, navigation, main UI elements
  - Hover: `#004aad`
  - Dark variant: `#002a66`

- **Accent Orange**: `#faa718`
  - Used for: CTAs, highlights, active states, important elements
  - Hover: `#ffc04d`
  - Dark variant: `#e89500`

- **White**: `#ffffff`
  - Used for: Text on dark backgrounds, cards, containers

### Semantic Colors
- **Success Green**: `#28a745` - Success messages, positive actions
- **Danger Red**: `#dc3545` - Errors, delete actions, warnings
- **Warning Orange**: `#faa718` - Warnings, caution messages (uses accent color)
- **Info Blue**: `#17a2b8` - Informational messages, help text

### Neutral Colors
- **Gray Scale**:
  - `#f8f9fa` - Light background
  - `#e9ecef` - Borders, dividers
  - `#dee2e6` - Input borders
  - `#6c757d` - Muted text
  - `#495057` - Secondary text
  - `#343a40` - Dark text
  - `#212529` - Primary text

## Usage Guidelines

### NO Gradients
- All colors are solid - no gradients anywhere in the application
- This ensures consistency with the logo and modern flat design

### Color Combinations
**Recommended Pairings:**
- Primary Blue (#003581) + White (#ffffff)
- Accent Orange (#faa718) + White (#ffffff)
- Primary Blue (#003581) + Accent Orange (#faa718)

**Avoid:**
- Orange on blue (low contrast)
- Light gray on white (accessibility issues)

### Accessibility
- Ensure text contrast ratio meets WCAG AA standards (4.5:1 minimum)
- White text on Primary Blue: ✓ High contrast
- White text on Accent Orange: ✓ High contrast
- Primary Blue text on white: ✓ High contrast

## Component Color Usage

### Buttons
- **Primary**: Blue (#003581) with white text
- **Accent/CTA**: Orange (#faa718) with white text
- **Success**: Green (#28a745) with white text
- **Danger**: Red (#dc3545) with white text

### Headers & Navigation
- **Background**: Primary Blue (#003581)
- **Text**: White (#ffffff)
- **Active/Hover**: Accent Orange (#faa718)

### Cards & Containers
- **Background**: White (#ffffff)
- **Border**: Light Gray (#e9ecef)
- **Header**: Primary Blue (#003581)

### Tables
- **Header**: Primary Blue (#003581) with white text
- **Rows**: Alternating white and light gray
- **Hover**: Light gray (#f8f9fa)

### Badges
- Follow semantic color scheme
- Use high contrast text colors

### Alerts
- Success: Green background
- Error: Red background
- Warning: Orange background
- Info: Blue background

## CSS Variables Reference

```css
:root {
    /* Brand Colors */
    --primary-color: #003581;
    --primary-dark: #002a66;
    --primary-light: #004aad;
    --accent-color: #faa718;
    --accent-dark: #e89500;
    --accent-light: #ffc04d;
    
    /* Semantic Colors */
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #faa718;
    --info-color: #17a2b8;
}
```

## Updated Files
All color references have been updated in:
- ✅ `sample/style.css` - Complete UI component library
- ✅ `sample/index.php` - Component showcase page
- ✅ `includes/header.php` - Main application header
- ✅ `includes/footer.php` - Main application footer
- ✅ `scripts/setup_db.php` - Database setup page

## Before/After
**Before**: Purple gradient theme (#667eea → #764ba2)
**After**: Blue & Orange solid colors (#003581, #faa718, #ffffff)

This creates a professional, consistent brand identity that matches the official Karyalay logo.
