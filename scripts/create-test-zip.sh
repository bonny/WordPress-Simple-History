#!/bin/bash

# Create test distribution zip for Simple History plugin
# This mimics the WordPress.org deployment process but creates a test zip
# for sending to users for testing development branches.

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "🔨 Building Simple History test distribution..."
echo ""

# 1. Check for uncommitted changes (warn but continue)
if ! git diff-index --quiet HEAD --; then
    echo -e "${YELLOW}⚠️  Warning: You have uncommitted changes${NC}"
    echo "   The zip will include these changes."
    echo ""
fi

# 2. Build the plugin
echo "📦 Installing dependencies and building..."
npm install --silent
npm run build --silent

# 3. Create temporary directory with correct plugin folder name
TEMP_DIR="/tmp/simple-history"
ZIP_NAME="simple-history-test.zip"

echo "🗂️  Creating clean distribution directory..."
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"

# 4. Copy files excluding patterns from .distignore
echo "📋 Copying files (excluding .distignore patterns)..."

# Use rsync to copy files while respecting .distignore
# Convert .distignore patterns to rsync exclude format
while IFS= read -r pattern; do
    # Skip empty lines and comments
    [[ -z "$pattern" || "$pattern" =~ ^# ]] && continue

    # Remove leading slash if present (rsync doesn't need it)
    pattern="${pattern#/}"

    EXCLUDES+=("--exclude=$pattern")
done < .distignore

# Copy all files except excluded ones
rsync -a "${EXCLUDES[@]}" \
    --exclude="$ZIP_NAME" \
    --exclude=".git" \
    . "$TEMP_DIR/"

# 5. Create the zip file
echo "🗜️  Creating zip archive..."
cd /tmp
rm -f "$ZIP_NAME"
zip -qr "$ZIP_NAME" simple-history/

# 6. Move zip to project root
mv "$ZIP_NAME" "$OLDPWD/"
cd "$OLDPWD"

# 7. Clean up
rm -rf "$TEMP_DIR"

# 8. Report success
FILE_SIZE=$(du -h "$ZIP_NAME" | cut -f1)
echo ""
echo -e "${GREEN}✓ Created $ZIP_NAME ($FILE_SIZE)${NC}"
echo ""
echo "📤 Ready to send to test users!"
echo "   When extracted, creates: wp-content/plugins/simple-history/"
echo ""
