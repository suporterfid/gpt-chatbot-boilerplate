# WordPress Blog Automation - Manual End-to-End Testing Checklist

## Overview

This document provides a comprehensive manual testing checklist for the WordPress Blog Automation system. All test cases should be executed in a staging or test environment before production deployment.

**Testing Date**: _______________
**Tester Name**: _______________
**Environment**: ☐ Development  ☐ Staging  ☐ Production
**Browser**: _______________
**Database**: ☐ SQLite  ☐ MySQL

---

## Pre-Testing Setup

### Environment Preparation

- [ ] Test environment is set up and running
- [ ] Database is clean or has known test data
- [ ] All services are running (web server, PHP-FPM)
- [ ] Test API keys configured (non-production)
- [ ] Test WordPress site accessible
- [ ] Browser console open for error monitoring
- [ ] Network tab open for API request monitoring

### Access Verification

- [ ] Admin panel accessible at `/admin`
- [ ] Login credentials working
- [ ] API authentication token available
- [ ] WordPress admin access confirmed

---

## Test Category 1: Configuration Management

### Test Case 1.1: Create New Configuration

**Steps**:
1. Navigate to Admin Panel → Blog Configurations
2. Click "Create New Configuration" button
3. Fill in all required fields:
   - Configuration Name: "Test Blog Config"
   - WordPress Site URL: https://test-site.com
   - WordPress Username: test_user
   - WordPress API Key: [test key]
   - OpenAI API Key: sk-test123...
   - Target Word Count: 2000
   - Max Internal Links: 5

**Expected Results**:
- [ ] Form validates in real-time
- [ ] All fields accept input without errors
- [ ] Required field indicators visible
- [ ] Save button becomes enabled when valid

**Actions**:
4. Click "Save Configuration"

**Expected Results**:
- [ ] Success message displayed
- [ ] Configuration appears in list
- [ ] API keys are masked in display (sk-****...)
- [ ] Configuration ID assigned
- [ ] Redirect to configurations list

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 1.2: Edit Existing Configuration

**Steps**:
1. From configurations list, click "Edit" on test configuration
2. Update Target Word Count to 2500
3. Update Max Internal Links to 7
4. Click "Save Changes"

**Expected Results**:
- [ ] Form pre-populated with existing values
- [ ] Changes save successfully
- [ ] Success message displayed
- [ ] Updated values reflected in list
- [ ] Other fields remain unchanged

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 1.3: Configuration Validation

**Steps**:
1. Create new configuration
2. Leave Configuration Name empty
3. Enter invalid URL: "not-a-url"
4. Enter invalid OpenAI key: "wrong-format"
5. Attempt to save

**Expected Results**:
- [ ] Validation errors displayed for each field
- [ ] Save button disabled or errors shown on submit
- [ ] Error messages are clear and helpful
- [ ] Form not submitted with invalid data
- [ ] No console errors

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 1.4: Add Internal Links

**Steps**:
1. Open configuration details
2. Navigate to "Internal Links" tab
3. Click "Add Internal Link"
4. Enter URL: https://test-site.com/article-1
5. Enter Anchor Text: "Related Article"
6. Click "Add"

**Expected Results**:
- [ ] Link added to repository
- [ ] Success message displayed
- [ ] Link appears in list
- [ ] URL and anchor text displayed correctly
- [ ] Delete button available for link

**Actions**:
7. Add 4 more internal links

**Expected Results**:
- [ ] All 5 links visible in list
- [ ] Links numbered or sorted
- [ ] No duplicates if same URL added

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 1.5: Delete Configuration

**Steps**:
1. Click "Delete" on a test configuration
2. Confirm deletion in modal/prompt

**Expected Results**:
- [ ] Confirmation dialog appears
- [ ] Warning about cascading deletion shown
- [ ] Configuration removed from list after confirmation
- [ ] Associated internal links deleted
- [ ] No errors in console

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

## Test Category 2: Article Queue Management

### Test Case 2.1: Queue New Article

**Steps**:
1. Navigate to Admin Panel → Article Queue
2. Click "Queue New Article" button
3. Select configuration from dropdown
4. Enter topic: "The Future of Artificial Intelligence"
5. Click "Add to Queue"

**Expected Results**:
- [ ] Article added to queue
- [ ] Article ID (UUID) generated and displayed
- [ ] Status shows as "pending"
- [ ] Article appears in queue list
- [ ] Success message displayed
- [ ] Form resets for next entry

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 2.2: View Queue with Filters

**Steps**:
1. On Article Queue page, use filter dropdown
2. Filter by status: "pending"
3. Clear filter
4. Filter by configuration ID
5. Use search/text filter if available

**Expected Results**:
- [ ] Filter applies immediately
- [ ] Correct articles shown for each filter
- [ ] Article count updates
- [ ] Clear filter button works
- [ ] Multiple filters can be combined
- [ ] No articles shown if filter matches none

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 2.3: View Article Details

**Steps**:
1. Click on an article in queue
2. Review article details panel/page

**Expected Results**:
- [ ] Article ID displayed
- [ ] Topic/title displayed
- [ ] Configuration name shown
- [ ] Current status displayed
- [ ] Created timestamp shown
- [ ] Processing timestamps (if applicable)
- [ ] Error message visible if failed
- [ ] Retry count shown
- [ ] View execution log link available

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 2.4: Delete Article from Queue

**Steps**:
1. Select an article in "pending" status
2. Click "Delete" or trash icon
3. Confirm deletion

**Expected Results**:
- [ ] Confirmation prompt appears
- [ ] Article removed from queue
- [ ] Success message displayed
- [ ] Execution logs for article also deleted
- [ ] No errors

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 2.5: Queue Auto-Refresh

**Steps**:
1. Queue an article
2. Start processing in CLI/background
3. Observe queue UI (should auto-refresh every 10s)

**Expected Results**:
- [ ] Status updates automatically
- [ ] Processing started timestamp appears
- [ ] Status changes: pending → processing → completed
- [ ] No manual refresh needed
- [ ] Refresh indicator shows during update
- [ ] Smooth UI updates without flickering

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

## Test Category 3: Article Processing

### Test Case 3.1: Process Single Article (Manual)

**Prerequisites**:
- Article queued with valid configuration
- Test API keys configured

**Steps**:
1. Open terminal/command line
2. Navigate to project directory
3. Run processor: `php scripts/wordpress_blog_processor.php --article-id=ARTICLE_ID`
4. Monitor console output

**Expected Results**:
- [ ] Processing starts immediately
- [ ] Console shows each stage:
  - [ ] Configuration loaded
  - [ ] Structure built
  - [ ] Content generated
  - [ ] Image generated
  - [ ] Published to WordPress
- [ ] No fatal errors
- [ ] Processing completes within expected time (<15 minutes)
- [ ] Final status: "completed"

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 3.2: Verify Processing Stages

**Steps**:
1. After processing completes, navigate to Article Queue
2. Find the processed article
3. Click "View Execution Log"

**Expected Results**:
- [ ] Execution log shows all stages:
  - [ ] queue
  - [ ] validation
  - [ ] structure
  - [ ] content
  - [ ] image
  - [ ] assets (if Google Drive configured)
  - [ ] publish
- [ ] Each stage has status: "completed"
- [ ] Execution time shown for each stage (ms)
- [ ] Timestamps in chronological order
- [ ] Total execution time calculated
- [ ] No errors in any stage

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 3.3: Verify WordPress Publication

**Steps**:
1. Log into WordPress admin panel
2. Navigate to Posts → All Posts
3. Find the published article
4. Open article for editing

**Expected Results**:
- [ ] Article exists in WordPress
- [ ] Title matches expected title
- [ ] Content is properly formatted
- [ ] Chapter headings use H2 tags
- [ ] Content is ~2000 words (or configured length)
- [ ] Featured image is set
- [ ] Featured image displays correctly
- [ ] Meta description is set (check SEO plugin or excerpt)
- [ ] Status is "draft" or "published" as configured
- [ ] No HTML errors or broken formatting

**Actions**:
5. Preview the article on frontend

**Expected Results**:
- [ ] Article displays correctly
- [ ] Images load
- [ ] Internal links work
- [ ] Formatting preserved
- [ ] No broken elements

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 3.4: Process Multiple Articles

**Steps**:
1. Queue 3 articles with different topics
2. Run processor: `php scripts/wordpress_blog_processor.php --mode=all`
3. Monitor processing

**Expected Results**:
- [ ] All 3 articles process sequentially
- [ ] Each completes successfully
- [ ] No interference between articles
- [ ] All publish to WordPress
- [ ] Execution logs separate for each

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

## Test Category 4: Error Handling

### Test Case 4.1: Invalid Configuration

**Steps**:
1. Create configuration with invalid WordPress API key
2. Queue article with this configuration
3. Attempt to process

**Expected Results**:
- [ ] Processing starts
- [ ] Configuration validation fails or WordPress publish fails
- [ ] Error message clear and actionable
- [ ] Article status set to "failed"
- [ ] Error logged in execution log
- [ ] Retry count not incremented (non-retryable error)
- [ ] No crash or fatal error

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 4.2: API Rate Limit (Simulated)

**Steps**:
1. Configure low rate limits in test environment
2. Queue multiple articles
3. Process rapidly
4. Trigger rate limit

**Expected Results**:
- [ ] Rate limit error detected
- [ ] Processing pauses (60-second delay for rate limits)
- [ ] Retry attempted after delay
- [ ] Eventually succeeds or reaches max retries
- [ ] Error handler logs rate limit detection
- [ ] No crash

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 4.3: Content Generation Failure

**Steps**:
1. Use invalid OpenAI API key
2. Queue and process article
3. Monitor error handling

**Expected Results**:
- [ ] Content generation fails
- [ ] Error caught by exception handler
- [ ] Retry attempted (exponential backoff)
- [ ] After max retries (3), article marked "failed"
- [ ] Error message stored
- [ ] Execution log shows failure
- [ ] No data corruption

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 4.4: Partial Processing Recovery

**Steps**:
1. Stop processing mid-execution (kill process)
2. Article should be stuck in "processing"
3. Manually reset status to "pending"
4. Re-run processing

**Expected Results**:
- [ ] Article can be reset to pending
- [ ] Re-processing works
- [ ] No duplicate content created
- [ ] Execution log shows multiple attempts
- [ ] Final attempt succeeds

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

## Test Category 5: Monitoring & Metrics

### Test Case 5.1: Processing Metrics Dashboard

**Steps**:
1. Navigate to Admin Panel → Blog Metrics
2. Review metrics display

**Expected Results**:
- [ ] Overview section shows:
  - [ ] Total articles
  - [ ] Completed count
  - [ ] Failed count
  - [ ] Success rate (%)
- [ ] Performance metrics show:
  - [ ] Average processing time
  - [ ] Total processing time
- [ ] Cost estimates displayed:
  - [ ] OpenAI costs
  - [ ] Replicate costs
  - [ ] Total costs
- [ ] Recent activity chart/list
- [ ] Metrics update when refreshed

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 5.2: System Health Check

**Steps**:
1. Navigate to health check page or run API call
2. Review health status

**Expected Results**:
- [ ] Overall status: "healthy" or "degraded"
- [ ] Database check: "pass"
- [ ] Disk space check: "pass" (with percentage)
- [ ] API keys validated: "pass"
- [ ] Queue status shown
- [ ] No stuck articles reported (or warning if stuck)
- [ ] Timestamp of last check

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 5.3: Execution Log Detail

**Steps**:
1. Select a completed article
2. View full execution log

**Expected Results**:
- [ ] All processing stages listed
- [ ] Each stage has:
  - [ ] Stage name
  - [ ] Status (completed/failed)
  - [ ] Message
  - [ ] Execution time (ms)
  - [ ] Timestamp
- [ ] Stages in chronological order
- [ ] Error details visible for failed stages
- [ ] Total execution time calculated
- [ ] Log is read-only (can't edit)

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

## Test Category 6: UI/UX Testing

### Test Case 6.1: Responsive Design

**Steps**:
1. Resize browser window to various widths:
   - Desktop (1920px)
   - Laptop (1366px)
   - Tablet (768px)
   - Mobile (375px)
2. Test each admin page at each size

**Expected Results**:
- [ ] Layout adapts to screen size
- [ ] No horizontal scrolling (except tables)
- [ ] Buttons remain accessible
- [ ] Forms stack vertically on mobile
- [ ] Tables scroll or collapse appropriately
- [ ] Navigation menu responsive
- [ ] Text remains readable

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 6.2: Loading States

**Steps**:
1. Navigate to configurations page
2. Observe loading indicator
3. Navigate to queue page while data loading
4. Trigger API call and observe

**Expected Results**:
- [ ] Loading spinner/indicator shown during data fetch
- [ ] Loading text descriptive ("Loading configurations...")
- [ ] Content doesn't jump when loaded
- [ ] Error state shown if load fails
- [ ] Retry button available on error
- [ ] Loading states consistent across pages

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 6.3: Error Messages and Toasts

**Steps**:
1. Trigger various errors (invalid input, API failure, etc.)
2. Trigger successes (save config, queue article)
3. Observe notifications

**Expected Results**:
- [ ] Success messages are green/positive
- [ ] Error messages are red/negative
- [ ] Warning messages are yellow/orange
- [ ] Messages auto-dismiss after 3-5 seconds
- [ ] Messages can be manually dismissed
- [ ] Multiple messages stack correctly
- [ ] Messages don't block UI interaction
- [ ] Text is clear and actionable

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 6.4: Navigation and Routing

**Steps**:
1. Navigate between pages using menu
2. Use browser back/forward buttons
3. Refresh page mid-navigation
4. Bookmark a page and return

**Expected Results**:
- [ ] Hash-based routing works (#wp-blog-configs, etc.)
- [ ] Page title updates per page
- [ ] Active menu item highlighted
- [ ] Back button works correctly
- [ ] Forward button works
- [ ] Refresh preserves current page
- [ ] Bookmarked URLs work
- [ ] No page flashing during navigation

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

## Test Category 7: Security Testing

### Test Case 7.1: Authentication

**Steps**:
1. Log out of admin panel
2. Attempt to access admin pages directly
3. Attempt API calls without token
4. Use invalid API token

**Expected Results**:
- [ ] Unauthenticated users redirected to login
- [ ] API calls without token return 401
- [ ] Invalid token returns 401
- [ ] Error message doesn't expose system details
- [ ] No access to protected resources

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 7.2: Credential Protection

**Steps**:
1. View configuration in admin UI
2. Inspect API responses in network tab
3. Check execution logs
4. Review database directly

**Expected Results**:
- [ ] API keys masked in UI (sk-****...)
- [ ] API responses mask credentials
- [ ] Credentials encrypted in database
- [ ] No plaintext credentials in logs
- [ ] No credentials in error messages
- [ ] Audit log doesn't contain plaintext keys

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 7.3: Input Validation

**Steps**:
1. Attempt SQL injection in form fields
2. Attempt XSS in text inputs
3. Try special characters in URLs
4. Submit extremely long inputs

**Expected Results**:
- [ ] SQL injection attempts sanitized
- [ ] XSS attempts escaped
- [ ] Special characters handled correctly
- [ ] Long inputs truncated or rejected
- [ ] Error messages don't execute scripts
- [ ] No SQL errors exposed to user

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

## Test Category 8: Performance Testing

### Test Case 8.1: Page Load Times

**Steps**:
1. Open DevTools → Network tab
2. Clear cache
3. Load each admin page
4. Record load times

**Expected Results**:
- [ ] Initial page load < 3 seconds
- [ ] Subsequent loads < 1 second
- [ ] API responses < 1 second
- [ ] No excessive API calls
- [ ] Assets cached appropriately

**Measurements**:
- Configurations page: _______ ms
- Queue page: _______ ms
- Metrics page: _______ ms

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

### Test Case 8.2: Large Dataset Handling

**Steps**:
1. Queue 50 articles
2. Load queue page
3. Apply filters
4. Scroll through list

**Expected Results**:
- [ ] Page loads without freezing
- [ ] Pagination or virtual scrolling works
- [ ] Filters apply quickly
- [ ] No memory leaks
- [ ] Browser remains responsive

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

## Browser Compatibility

### Test Case 9.1: Cross-Browser Testing

Test the following browsers:

**Chrome/Chromium**:
- [ ] All features work
- [ ] UI renders correctly
- [ ] No console errors

**Firefox**:
- [ ] All features work
- [ ] UI renders correctly
- [ ] No console errors

**Safari** (if available):
- [ ] All features work
- [ ] UI renders correctly
- [ ] No console errors

**Edge**:
- [ ] All features work
- [ ] UI renders correctly
- [ ] No console errors

**Status**: ☐ Pass  ☐ Fail  ☐ Skip

**Notes**: _______________________________________________

---

## Console and Network Monitoring

### Throughout All Tests

**Browser Console**:
- [ ] No JavaScript errors
- [ ] No unhandled promise rejections
- [ ] No deprecation warnings
- [ ] Only expected log messages

**Network Tab**:
- [ ] All API calls return 200/201
- [ ] No 400/500 errors (except when testing errors)
- [ ] Request payloads correctly formatted
- [ ] Response data as expected
- [ ] No unnecessary requests
- [ ] Proper authentication headers

**Performance Tab**:
- [ ] No memory leaks during extended use
- [ ] CPU usage reasonable
- [ ] No long-running tasks blocking UI

---

## Post-Testing Summary

### Test Results Summary

**Total Test Cases**: _______
**Passed**: _______
**Failed**: _______
**Skipped**: _______
**Pass Rate**: _______%

### Critical Issues Found

1. _____________________________________________
2. _____________________________________________
3. _____________________________________________

### Non-Critical Issues Found

1. _____________________________________________
2. _____________________________________________
3. _____________________________________________

### Recommendations

1. _____________________________________________
2. _____________________________________________
3. _____________________________________________

### Sign-Off

**Tester Signature**: _______________
**Date**: _______________
**Approved for Production**: ☐ Yes  ☐ No  ☐ Conditional

**Conditions for Approval** (if conditional):
_____________________________________________
_____________________________________________

---

**Document Version**: 1.0
**Last Updated**: November 21, 2025
**Next Review**: After bug fixes
