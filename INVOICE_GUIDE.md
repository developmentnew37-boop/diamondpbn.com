# Invoice Generator - User Guide

## Accessing the Invoice Generator

Navigate to: **Admin Panel → Addons → Invoice Generator**
Or directly: `/admin/invoice/generator`

---

## Creating an Invoice

### Step 1: Company Information
- Upload your company logo (optional, PNG/JPG, max 2MB)
- Enter company name (required)
- Add company email, phone, and address

### Step 2: Client Information
- Enter client name (required)
- Add client email, phone, and address

### Step 3: Invoice Details
- Invoice number (auto-generated, editable)
- Invoice date (defaults to today)
- Due date (defaults to 30 days from today)

### Step 4: Theme Customization ⭐
Choose from 8 premium color themes:
- **Classic Blue** (#4f79bd) - Professional and trustworthy
- **Emerald Green** (#10b981) - Fresh and modern
- **Orange** (#f97316) - Energetic and bold
- **Purple** (#8b5cf6) - Creative and elegant
- **Red** (#ef4444) - Strong and attention-grabbing
- **Cyan** (#06b6d4) - Cool and contemporary
- **Pink** (#ec4899) - Vibrant and unique
- **Teal** (#14b8a6) - Balanced and professional
- **Custom Color** - Pick any color you want!

### Step 5: Add Items/Services
- Click "+ Add Item" to add products or services
- Enter description, price, and quantity
- Subtotals calculate automatically
- Add up to 50 items per invoice

### Step 6: Additional Options
- Tax rate (percentage)
- Discount amount (fixed dollar amount)
- Notes/Terms (payment terms, thank you message, etc.)

### Step 7: Generate PDF
- Click "Generate PDF Invoice"
- PDF downloads automatically
- Professional, print-ready format

---

## Premium Features

✅ **Dynamic Theme Colors** - Brand your invoices with your colors
✅ **Professional Typography** - Clean, readable fonts
✅ **Automatic Calculations** - Real-time totals, tax, and discounts
✅ **Responsive Design** - Optimized for A4 paper
✅ **Logo Support** - Add your company branding
✅ **Dual-Party Display** - Shows both company and client info
✅ **Watermark Effect** - Subtle background branding
✅ **Premium Footer** - Professional contact information
✅ **Signature Section** - Space for authorized signature
✅ **Small File Size** - Fast generation and download (~9KB)

---

## Testing the Invoice Generator

Use the command line to generate test invoices:

```bash
# Generate with default blue theme
php artisan invoice:test

# Generate with custom theme color
php artisan invoice:test --theme="#10b981"
php artisan invoice:test --theme="#8b5cf6"
php artisan invoice:test --theme="#f97316"
```

Test invoices are saved to: `storage/app/public/test-invoice-*.pdf`

---

## Tips for Best Results

1. **Logo**: Use a square or horizontal logo (recommended: 500x500px)
2. **Theme Color**: Choose colors that match your brand
3. **Notes**: Include payment terms, bank details, or thank you message
4. **Item Descriptions**: Be clear and specific
5. **Professional Email**: Use a business email address

---

## Color Psychology for Invoices

- **Blue**: Trust, stability, professionalism (best for corporate)
- **Green**: Growth, prosperity, eco-friendly
- **Purple**: Luxury, creativity, premium services
- **Orange**: Energy, enthusiasm, friendly
- **Red**: Urgency, importance, bold statements
- **Teal/Cyan**: Modern, tech-savvy, innovative

---

## Troubleshooting

**Issue**: PDF not generating
- Check all required fields are filled
- Ensure at least one item is added
- Verify logo file is under 2MB

**Issue**: Theme color not applying
- Make sure you selected a theme before generating
- Try using the custom color picker

**Issue**: Layout looks off
- This is optimized for A4 paper size
- Use "Print to PDF" or "Save as PDF" for best results

---

## Future Enhancements (Available on Request)

- Invoice history and database storage
- Email invoice directly to clients
- Recurring invoice templates
- Multiple currency support
- Invoice numbering automation
- Client database management
- Payment tracking
- Invoice status (Paid/Unpaid/Overdue)

---

Generated with ❤️ by Diamond PBN Invoice Generator
