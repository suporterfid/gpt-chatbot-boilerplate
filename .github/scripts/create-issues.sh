#!/bin/bash
# Script to create GitHub issues from code review templates
# Requires: GitHub CLI (gh) installed and authenticated
#
# Usage:
#   1. Install gh: https://cli.github.com/
#   2. Authenticate: gh auth login
#   3. Run: bash .github/scripts/create-issues.sh

set -e

REPO="suporterfid/gpt-chatbot-boilerplate"
ISSUES_DIR=".github/issues"

echo "🚀 Creating GitHub issues from code review templates..."
echo "Repository: $REPO"
echo ""

# Check if gh is installed
if ! command -v gh &> /dev/null; then
    echo "❌ Error: GitHub CLI (gh) is not installed"
    echo "Install it from: https://cli.github.com/"
    exit 1
fi

# Check if authenticated
if ! gh auth status &> /dev/null; then
    echo "❌ Error: Not authenticated with GitHub"
    echo "Run: gh auth login"
    exit 1
fi

# Function to create issue from markdown file
create_issue() {
    local file=$1
    local labels=$2

    if [ ! -f "$file" ]; then
        echo "⚠️  Skipping $file (not found)"
        return
    fi

    echo "📝 Creating issue from: $(basename $file)"

    # Extract title (first heading, remove markdown #)
    title=$(grep -m 1 "^# " "$file" | sed 's/^# \[.*\] //' | sed 's/^# //')

    # Create the issue
    issue_url=$(gh issue create \
        --repo "$REPO" \
        --title "$title" \
        --body-file "$file" \
        --label "$labels" 2>&1)

    if [ $? -eq 0 ]; then
        echo "✅ Created: $title"
        echo "   URL: $issue_url"
    else
        echo "❌ Failed to create: $title"
        echo "   Error: $issue_url"
    fi
    echo ""
}

# Create master tracking issue first
echo "=== Creating Master Tracking Issue ==="
create_issue "$ISSUES_DIR/MASTER-code-review-wordpress-blog.md" "priority: critical,type: tracking"

echo ""
echo "=== Creating Critical Issues (Blocking) ==="
create_issue "$ISSUES_DIR/critical-1-multi-tenancy.md" "priority: critical,type: security,type: bug"
create_issue "$ISSUES_DIR/critical-2-resource-authorization.md" "priority: critical,type: security,type: bug"

echo ""
echo "=== Creating High Priority Issues ==="
create_issue "$ISSUES_DIR/high-1-csrf-protection.md" "priority: high,type: security"

echo ""
echo "=== Creating Medium Priority Issues ==="
create_issue "$ISSUES_DIR/medium-1-n-plus-one-query.md" "priority: medium,type: performance"

echo ""
echo "✨ Done! All issues created successfully!"
echo ""
echo "View all issues at:"
echo "https://github.com/$REPO/issues"
echo ""
echo "Next steps:"
echo "1. Review the created issues"
echo "2. Update the master issue with actual issue numbers"
echo "3. Assign issues to team members"
echo "4. Add to milestone: WordPress Blog v1.0.1 Fixes"
