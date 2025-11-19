#!/bin/bash

##############################################################################
# Deployment Package Builder
#
# Creates a production-ready ZIP package for cloud hosting (e.g., Hostinger)
# Can run both locally and in CI/CD environments
#
# Usage:
#   ./scripts/build-deployment.sh [output-filename]
#
# Example:
#   ./scripts/build-deployment.sh chatbot-deploy.zip
##############################################################################

set -e  # Exit on error

# Configuration
OUTPUT_FILE="${1:-chatbot-deploy.zip}"
BUILD_DIR="build/deployment"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
print_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "  $1"
}

# Start build process
print_header "Building Deployment Package"
echo -e "Output file: ${GREEN}$OUTPUT_FILE${NC}"
echo -e "Build directory: ${GREEN}$BUILD_DIR${NC}"
echo ""

# Clean previous build
if [ -d "$BUILD_DIR" ]; then
    print_info "Cleaning previous build directory..."
    rm -rf "$BUILD_DIR"
fi

mkdir -p "$BUILD_DIR"
print_success "Build directory created"

# Copy production files
print_header "Copying Production Files"

# Define files and directories to include
INCLUDE_PATTERNS=(
    "*.php"
    "*.css"
    "*.js"
    ".htaccess"
    "favicon.ico"
    "api"
    "assets"
    "channels"
    "db/migrations"
    "includes"
    "public"
    "webhooks"
    "composer.json"
)

# Copy files
for pattern in "${INCLUDE_PATTERNS[@]}"; do
    if [[ "$pattern" == *"/"* ]]; then
        # Directory path
        if [ -e "$pattern" ]; then
            mkdir -p "$BUILD_DIR/$(dirname $pattern)"
            cp -r "$pattern" "$BUILD_DIR/$pattern"
            print_success "Copied: $pattern"
        else
            print_warning "Skipped (not found): $pattern"
        fi
    else
        # File pattern (root level)
        files_found=false
        for file in $pattern; do
            if [ -f "$file" ]; then
                cp "$file" "$BUILD_DIR/"
                print_success "Copied: $file"
                files_found=true
            fi
        done
        if [ "$files_found" = false ]; then
            print_warning "No files matched pattern: $pattern"
        fi
    fi
done

# Remove .backup files
find "$BUILD_DIR" -name "*.backup" -type f -delete
print_info "Removed backup files"

# Install Composer dependencies (production only)
print_header "Installing Production Dependencies"

if [ -f "composer.json" ]; then
    if command -v composer &> /dev/null; then
        cd "$BUILD_DIR"
        composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader 2>&1 | grep -v "^$"
        cd - > /dev/null
        print_success "Composer dependencies installed"
    else
        print_warning "Composer not found - dependencies must be installed on target server"
        print_info "Make sure your hosting environment runs: composer install --no-dev --optimize-autoloader"
    fi
else
    print_warning "composer.json not found"
fi

# Create .env template (not actual .env with secrets)
print_header "Creating Environment Template"

if [ -f ".env.example" ]; then
    cp ".env.example" "$BUILD_DIR/.env.example"
    print_success "Copied .env.example"
    print_warning "Remember to configure .env on the server!"
else
    print_warning ".env.example not found"
fi

# Create deployment info file
print_header "Creating Deployment Metadata"

cat > "$BUILD_DIR/DEPLOYMENT_INFO.txt" << EOF
Deployment Package Information
================================
Generated: $(date '+%Y-%m-%d %H:%M:%S %Z')
Git Branch: $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")
Git Commit: $(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
Built By: $(whoami)
Build Host: $(hostname)

IMPORTANT: Post-Deployment Steps
=================================
1. Upload this package to your hosting service
2. Extract to your web root directory
3. Configure .env file with your credentials:
   - Database connection (DB_*)
   - OpenAI API key (OPENAI_API_KEY)
   - Admin credentials (ADMIN_TOKEN)
   - Application URL (BASE_URL)
4. Set proper file permissions (755 for directories, 644 for files)
5. Ensure writable directories:
   - logs/ (create if not exists)
   - data/ (create if not exists)
6. Run database migrations:
   php scripts/run_migrations.php
7. Test the deployment:
   - Access your-domain.com/
   - Verify admin panel access
   - Test chat functionality

Required PHP Extensions:
========================
- PHP >= 8.0
- ext-curl
- ext-json
- ext-mbstring
- ext-pdo
- ext-pdo_mysql (or pdo_sqlite)

Security Checklist:
===================
[ ] .env file configured and NOT publicly accessible
[ ] Admin token is strong and unique
[ ] Database credentials are secure
[ ] File permissions are correct
[ ] HTTPS is enabled
[ ] Error display is disabled (display_errors = Off)
[ ] logs/ directory is not web-accessible

For issues or questions, refer to: README.md
EOF

print_success "Created DEPLOYMENT_INFO.txt"

# Create ZIP archive
print_header "Creating ZIP Archive"

if [ -f "$OUTPUT_FILE" ]; then
    rm "$OUTPUT_FILE"
    print_info "Removed existing $OUTPUT_FILE"
fi

cd "$BUILD_DIR"
zip -r "../../$OUTPUT_FILE" . -q
cd - > /dev/null

if [ -f "$OUTPUT_FILE" ]; then
    FILE_SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)
    print_success "ZIP archive created: $OUTPUT_FILE ($FILE_SIZE)"
else
    print_error "Failed to create ZIP archive"
    exit 1
fi

# Generate checksum
print_header "Generating Checksum"

if command -v sha256sum &> /dev/null; then
    CHECKSUM=$(sha256sum "$OUTPUT_FILE" | cut -d' ' -f1)
    echo "$CHECKSUM  $OUTPUT_FILE" > "${OUTPUT_FILE}.sha256"
    print_success "SHA256: $CHECKSUM"
    print_info "Checksum saved to: ${OUTPUT_FILE}.sha256"
elif command -v shasum &> /dev/null; then
    CHECKSUM=$(shasum -a 256 "$OUTPUT_FILE" | cut -d' ' -f1)
    echo "$CHECKSUM  $OUTPUT_FILE" > "${OUTPUT_FILE}.sha256"
    print_success "SHA256: $CHECKSUM"
    print_info "Checksum saved to: ${OUTPUT_FILE}.sha256"
else
    print_warning "sha256sum/shasum not found - skipping checksum"
fi

# Summary
print_header "Build Complete!"

echo ""
echo "Deployment package ready:"
echo "  📦 Package: $OUTPUT_FILE ($FILE_SIZE)"
if [ -f "${OUTPUT_FILE}.sha256" ]; then
    echo "  🔒 Checksum: ${OUTPUT_FILE}.sha256"
fi
echo ""
echo "Next steps:"
echo "  1. Review DEPLOYMENT_INFO.txt inside the ZIP"
echo "  2. Upload to your hosting service (e.g., Hostinger)"
echo "  3. Configure .env with your production credentials"
echo "  4. Run database migrations"
echo "  5. Test the deployment"
echo ""
print_warning "Remember: NEVER commit .env files with actual credentials!"
echo ""
