#!/bin/bash

# Invoice Generator - Quick Demo Script
# This script generates sample invoices with different themes

echo "🎨 Invoice Generator - Demo Script"
echo "=================================="
echo ""

# Array of theme colors
declare -a themes=(
    "#4f79bd:Classic Blue"
    "#10b981:Emerald Green"
    "#f97316:Orange"
    "#8b5cf6:Purple"
    "#ef4444:Red"
    "#06b6d4:Cyan"
    "#ec4899:Pink"
    "#14b8a6:Teal"
)

echo "Generating sample invoices with different themes..."
echo ""

# Generate invoice for each theme
for theme in "${themes[@]}"; do
    IFS=':' read -r color name <<< "$theme"
    echo "📄 Generating invoice with $name theme ($color)..."
    php artisan invoice:test --theme="$color"
    sleep 1
done

echo ""
echo "✅ Demo complete!"
echo ""
echo "📁 Generated invoices saved to: storage/app/public/"
echo "🌐 Open the folder to view your sample invoices"
echo ""
echo "Next steps:"
echo "1. Review the generated PDFs"
echo "2. Access the invoice generator at: /admin/invoice/generator"
echo "3. Create your first real invoice!"
echo ""
