#!/bin/bash
# =============================================================================
# MSP Reports - Build & Deploy Script
# =============================================================================
# Builds the React frontend and copies the output to backend/public
# so Nginx can serve everything from one location.
#
# Usage:
#   ./build.sh          # Full build (frontend + copy to backend)
#   ./build.sh --skip-install  # Skip npm install (faster rebuild)
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRONTEND_DIR="$SCRIPT_DIR/frontend"
BACKEND_PUBLIC="$SCRIPT_DIR/backend/public"
BUILD_DIR="$FRONTEND_DIR/dist"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  MSP Reports - Build Script${NC}"
echo -e "${GREEN}========================================${NC}"

# -----------------------------------------------------------------------------
# Step 1: Check prerequisites
# -----------------------------------------------------------------------------
echo -e "\n${YELLOW}[1/5] Checking prerequisites...${NC}"

if ! command -v node &> /dev/null; then
    echo -e "${RED}Error: Node.js is not installed${NC}"
    exit 1
fi

if ! command -v npm &> /dev/null; then
    echo -e "${RED}Error: npm is not installed${NC}"
    exit 1
fi

echo "  Node: $(node -v)"
echo "  npm:  $(npm -v)"

# -----------------------------------------------------------------------------
# Step 2: Install dependencies
# -----------------------------------------------------------------------------
if [ "$1" != "--skip-install" ]; then
    echo -e "\n${YELLOW}[2/5] Installing frontend dependencies...${NC}"
    cd "$FRONTEND_DIR"
    npm install
else
    echo -e "\n${YELLOW}[2/5] Skipping npm install (--skip-install)${NC}"
fi

# -----------------------------------------------------------------------------
# Step 3: Build frontend
# -----------------------------------------------------------------------------
echo -e "\n${YELLOW}[3/5] Building frontend (production)...${NC}"
cd "$FRONTEND_DIR"
npm run build

if [ ! -d "$BUILD_DIR" ]; then
    echo -e "${RED}Error: Build failed - dist directory not found${NC}"
    exit 1
fi

# -----------------------------------------------------------------------------
# Step 4: Clean old frontend files from backend/public
# -----------------------------------------------------------------------------
echo -e "\n${YELLOW}[4/5] Cleaning old frontend files from backend/public...${NC}"

# Remove old Vite build assets but keep Laravel files
rm -rf "$BACKEND_PUBLIC/assets"
rm -f "$BACKEND_PUBLIC/vite.svg"

# Remove old frontend index.html (we'll replace it)
# Keep: index.php, .htaccess, robots.txt, favicon.ico, storage symlink, logos
KEEP_FILES=("index.php" ".htaccess" "robots.txt" "favicon.ico" "storage" "logos" "Athletics" "js")

# -----------------------------------------------------------------------------
# Step 5: Copy build files to backend/public
# -----------------------------------------------------------------------------
echo -e "\n${YELLOW}[5/5] Copying build files to backend/public...${NC}"

# Copy all built files
cp -r "$BUILD_DIR"/* "$BACKEND_PUBLIC/"

# Ensure Laravel's index.php is not overwritten
if [ ! -f "$BACKEND_PUBLIC/index.php" ]; then
    echo -e "${RED}Warning: index.php was overwritten! Restoring...${NC}"
    cd "$SCRIPT_DIR/backend"
    git checkout public/index.php
fi

# Count files copied
FILE_COUNT=$(find "$BUILD_DIR" -type f | wc -l)
BUILD_SIZE=$(du -sh "$BUILD_DIR" | cut -f1)

echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}  Build Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "  Files copied: ${FILE_COUNT}"
echo -e "  Build size:   ${BUILD_SIZE}"
echo -e "  Output:       ${BACKEND_PUBLIC}"
echo -e ""
echo -e "  Frontend is now served from backend/public"
echo -e "  Access at: ${YELLOW}http://192.168.9.85:8081${NC}"
echo -e "${GREEN}========================================${NC}"
