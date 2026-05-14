# 📄 Invoice Generator System

## Quick Start

### Access the Invoice Generator
1. Log into your Diamond PBN admin panel
2. Navigate to **Addons → Invoice Generator** in the sidebar
3. Fill in the form and generate your first invoice!

---

## 🚀 5-Minute Setup Guide

### Step 1: Create Your First Invoice

**Company Information:**
```
Company Name: Your Company Name
Email: your@company.com
Phone: +1 234 567 8900
Address: 123 Business St, City, State 12345
```

**Client Information:**
```
Client Name: Client Company Name
Email: client@example.com
Phone: +1 987 654 3210
Address: 456 Client Ave, City, State 67890
```

**Invoice Details:**
```
Invoice Number: INV-20260513-001 (auto-generated)
Invoice Date: Today's date
Due Date: 30 days from today
```

**Choose Theme Color:**
- Click on any color swatch (Blue, Green, Orange, Purple, etc.)
- Or use the custom color picker for your brand color

**Add Items:**
```
Item 1: Web Development Services | $150/hr | 10 hours = $1,500
Item 2: Hosting Setup | $50 | 1 = $50
Item 3: Domain Registration | $15 | 1 = $15
```

**Optional:**
```
Tax Rate: 10%
Discount: $50
Notes: Payment due within 30 days. Thank you!
```

**Generate:**
- Click "Generate PDF Invoice"
- PDF downloads automatically
- Ready to send to your client!

---

## 🎨 Branding Your Invoices

### Upload Your Logo
1. Click the logo upload area
2. Select your logo (PNG, JPG, GIF)
3. Max size: 2MB
4. Recommended: 500x500px square or horizontal logo

### Choose Your Brand Color
**Option 1: Use Presets**
- Click any of the 8 preset color swatches
- Colors are professionally selected

**Option 2: Custom Color**
- Click the "Custom" color picker
- Enter your exact brand color
- Supports any hex color code

---

## 💡 Pro Tips

### Invoice Numbering
Use a consistent format:
- `INV-YYYYMMDD-001` (Date-based)
- `INV-2026-001` (Year-based)
- `CLIENT-001` (Client-based)

### Payment Terms
Include in notes:
- Payment due date
- Accepted payment methods
- Bank details or PayPal email
- Late payment fees (if applicable)

### Professional Touch
- Always include your logo
- Use consistent brand colors
- Be clear and specific in item descriptions
- Include contact information
- Add a thank you note

---

## 🧪 Testing

### Generate Test Invoice
```bash
# Default blue theme
php artisan invoice:test

# Custom theme color
php artisan invoice:test --theme="#10b981"
php artisan invoice:test --theme="#8b5cf6"
php artisan invoice:test --theme="#f97316"
```

Test PDFs are saved to: `storage/app/public/`

---

## 📱 Mobile & Print

### Mobile Access
- Fully responsive design
- Works on tablets and phones
- Touch-friendly interface

### Printing
- Optimized for A4 paper
- Print-ready PDF format
- Professional quality output

---

## 🎯 Common Use Cases

### Freelancers
```
Services: Hourly rate × hours worked
Add: Project description
Include: Payment terms
```

### Agencies
```
Services: Multiple line items
Add: Project phases
Include: Retainer information
```

### Consultants
```
Services: Day rate or project fee
Add: Deliverables
Include: Milestone payments
```

### Product Sales
```
Products: Item name, unit price, quantity
Add: Shipping costs
Include: Return policy
```

---

## 🔧 Troubleshooting

### PDF Not Generating?
✅ Check all required fields are filled
✅ Ensure at least one item is added
✅ Verify logo is under 2MB
✅ Check browser console for errors

### Theme Color Not Showing?
✅ Click the color swatch to select
✅ For custom colors, use the color picker
✅ Refresh the page if needed

### Logo Not Appearing?
✅ Use PNG, JPG, or GIF format
✅ Keep file size under 2MB
✅ Try a different image if issues persist

### Calculations Wrong?
✅ Check item prices and quantities
✅ Verify tax rate is a percentage (not decimal)
✅ Discount is a fixed dollar amount

---

## 📊 Features Overview

| Feature | Description | Status |
|---------|-------------|--------|
| Theme Colors | 9 color options | ✅ Active |
| Logo Upload | Brand your invoices | ✅ Active |
| Real-time Calc | Instant totals | ✅ Active |
| Tax Support | Percentage-based | ✅ Active |
| Discounts | Fixed amount | ✅ Active |
| PDF Export | Download ready | ✅ Active |
| Responsive | Mobile-friendly | ✅ Active |
| Professional | Premium design | ✅ Active |

---

## 🎓 Best Practices

### DO ✅
- Use professional email addresses
- Include clear item descriptions
- Set realistic due dates
- Add payment instructions
- Keep consistent numbering
- Include all contact info
- Proofread before sending

### DON'T ❌
- Use generic descriptions
- Forget to add due dates
- Skip payment terms
- Use low-quality logos
- Leave fields empty
- Rush the process

---

## 📞 Need Help?

### Documentation
- **User Guide**: `INVOICE_GUIDE.md`
- **Implementation**: `INVOICE_IMPLEMENTATION.md`
- **This File**: `README_INVOICE.md`

### Testing
```bash
php artisan invoice:test --theme="#your-color"
```

### Support
Check the main project documentation or contact your system administrator.

---

## 🎉 You're Ready!

Your invoice generator is fully set up and ready to use. Start creating professional invoices in minutes!

**Quick Access**: `/admin/invoice/generator`

---

**Made with ❤️ for Diamond PBN**
*Professional invoicing made simple*
