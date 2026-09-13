# Performance Analytics Dashboard - Implementation Guide

## Overview
A comprehensive hierarchical analytics dashboard for OrdinaTrack that displays province, district, and branch performance metrics with drill-down capability, ranked from best to worst performers.

## Features

### 1. Hierarchical Data Structure
- **Province Level**: Overview of all provinces with aggregated metrics
- **District Level**: Drill down into specific province to see district performance
- **Branch Level**: Drill down into specific district to see branch performance

### 2. Performance Metrics
Each location shows:
- **Performance Score** (0-100%): Weighted calculation
  - Active Members: 50%
  - Branches/Locations: 30%
  - District Count (provinces only): 20%
- **Active Members**: Count of active organization members
- **Locations**: District count (province) or Branch count (district)
- **Activity Status**: Active, Moderate, Inactive, Dormant, No Data

### 3. Sorting & Ranking
All levels automatically sorted from highest to lowest performance by:
1. Active Members (DESC)
2. Branch/District Count (DESC)
3. Creation Date

### 4. Visualizations
The dashboard includes 4 dynamic charts:

#### Performance Score Distribution (Bar Chart)
- Top 10 performers ranked by performance score
- Shows complete score breakdown
- Updates based on current drill-level

#### Members Distribution (Doughnut Chart)
- Top 8 locations by member count
- Visual proportion of member distribution
- Percentage breakdown in tooltips

#### Top 5 Performers (Radar Chart)
- Multi-dimensional performance comparison
- Shows performance scores across top 5 performers
- Ideal for identifying outliers

#### Activity Status Distribution (Pie Chart)
- Breakdown of locations by activity status
- Counts and percentages
- Color-coded by status level

### 5. Navigation Features
- **Breadcrumb Navigation**: Shows current location in hierarchy
- **Clickable Rows**: Click any row to drill down to next level
- **Back Navigation**: Click breadcrumb items to navigate back
- **Status Indicators**: Color-coded badges for quick status assessment

### 6. Overall Statistics
Dashboard displays system-wide metrics:
- Total Provinces
- Total Districts
- Total Branches
- Active Members
- Active Users
- Active Locations (in last 7 days)

## API Endpoints

### GET /dashboard/provinces
Get all provinces with performance stats (sorted best to worst)
```
Query Params:
  - limit: Number of results (default: 50, max: 100)
  - offset: Pagination offset (default: 0)

Response:
  {
    "success": true,
    "data": [
      {
        "id": 1,
        "name": "OKRIKA PROVINCIAL HQ",
        "code": "OKR",
        "district_count": 6,
        "branch_count": 24,
        "active_members": 150,
        "active_users": 8,
        "performance_score": 87,
        "status": "active",
        "trend": [ ... ]
      }
    ],
    "pagination": {
      "limit": 50,
      "offset": 0,
      "count": 12
    }
  }
```

### GET /dashboard/districts/:provinceId
Get districts for a specific province (sorted best to worst)
```
Query Params:
  - limit: Number of results (default: 50, max: 100)
  - offset: Pagination offset (default: 0)

Response:
  {
    "success": true,
    "data": [
      {
        "id": 5,
        "name": "OKRIKA CENTRAL DISTRICT",
        "code": "OKR001",
        "province_id": 1,
        "province_name": "OKRIKA PROVINCIAL HQ",
        "branch_count": 4,
        "active_members": 45,
        "active_users": 3,
        "performance_score": 92,
        "status": "active",
        "trend": [ ... ]
      }
    ],
    "pagination": { ... }
  }
```

### GET /dashboard/branches/:districtId
Get branches for a specific district (sorted best to worst)
```
Query Params:
  - limit: Number of results (default: 50, max: 100)
  - offset: Pagination offset (default: 0)

Response:
  {
    "success": true,
    "data": [
      {
        "id": 12,
        "name": "TOMO-BIRI",
        "code": "OKR001-BR001",
        "district_id": 5,
        "province_id": 1,
        "district_name": "OKRIKA CENTRAL DISTRICT",
        "province_name": "OKRIKA PROVINCIAL HQ",
        "active_members": 15,
        "active_users": 1,
        "contact_person": "John Doe",
        "contact_email": "contact@branch.com",
        "performance_score": 85,
        "status": "active",
        "trend": [ ... ]
      }
    ],
    "pagination": { ... }
  }
```

### GET /dashboard/province/:provinceId/hierarchy
Get complete province hierarchy (nested structure)
```
Response:
  {
    "success": true,
    "data": {
      "id": 1,
      "name": "OKRIKA PROVINCIAL HQ",
      "district_count": 6,
      "branch_count": 24,
      "active_members": 150,
      "performance_score": 87,
      "districts": [
        {
          "id": 5,
          "name": "OKRIKA CENTRAL DISTRICT",
          "branch_count": 4,
          "active_members": 45,
          "branches": [
            {
              "id": 12,
              "name": "TOMO-BIRI",
              "active_members": 15
            }
          ]
        }
      ]
    }
  }
```

### GET /dashboard/stats
Get overall system statistics
```
Response:
  {
    "success": true,
    "data": {
      "total_provinces": 12,
      "total_districts": 87,
      "total_branches": 542,
      "total_members": 3250,
      "total_users": 156,
      "active_locations": 45
    }
  }
```

## Database Models

### DashboardModel
Located at: `/API/src/Model/DashboardModel.php`

**Key Methods:**
- `getProvinceStats($limit, $offset)` - Get provinces with stats
- `getDistrictStats($provinceId, $limit, $offset)` - Get districts for province
- `getBranchStats($districtId, $limit, $offset)` - Get branches for district
- `getProvinceHierarchy($provinceId)` - Get complete nested hierarchy
- `getOverallStats()` - Get system-wide statistics
- `calculatePerformanceScore($members, $branches, $districts)` - Calculate 0-100 score

### Performance Ranking Logic
All queries use ORDER BY for automatic sorting:
```sql
ORDER BY active_members DESC, branch_count DESC, district_count DESC
```

This ensures best performers (most members) appear first.

## Frontend Files

### Main Dashboard
**Location:** `/App/dashboard/nhq/analytics.html`

**Key JavaScript Functions:**
- `initDashboard()` - Initialize on page load
- `loadOverallStats()` - Fetch and display system stats
- `loadProvinces()` - Load province-level data
- `loadDistricts(provinceId, provinceName)` - Drill down to districts
- `loadBranches(districtId, districtName, provinceName)` - Drill down to branches
- `generateCharts(items, type)` - Create all visualizations
- `updateBreadcrumb(level2, level1)` - Update navigation breadcrumb

**Chart Functions:**
- `createPerformanceChart()` - Bar chart of performance scores
- `createMembersChart()` - Doughnut chart of member distribution
- `createTopPerformersChart()` - Radar chart of top 5
- `createStatusChart()` - Pie chart of activity status

## Usage

### Access the Dashboard
Navigate to: `http://localhost/ordinatrack/App/dashboard/nhq/analytics.html`

### Requirements
- Valid authentication token (stored in localStorage)
- API must be running and accessible
- Charts.js library loaded (CDN)

### Drill-Down Workflow
1. View all provinces ranked by performance
2. Click on a province row to see its districts
3. Click on a district row to see its branches
4. Use breadcrumb to navigate back

### Understanding Performance Score
- **90-100**: Excellent (highest performers)
- **75-89**: Good (strong performers)
- **50-74**: Average (moderate performers)
- **Below 50**: Needs attention (lowest performers)

## Data Refresh
Charts and tables are loaded fresh each time:
- User navigates to the dashboard
- User clicks to drill down
- User navigates back via breadcrumb

No client-side caching, ensuring real-time data accuracy.

## Status Meanings
- **Active**: Activity within last 7 days
- **Moderate**: Activity 8-30 days ago
- **Inactive**: Activity 31-90 days ago
- **Dormant**: No activity for 90+ days
- **No Data**: No activity records found

## Customization

### Change Performance Score Weights
Edit `DashboardModel.php` `calculatePerformanceScore()` method:
```php
// Current weights: 50% members, 30% branches, 20% districts
$memberScore = min(($members / 1000) * 50, 50);
$branchScore = min(($branches / 100) * 30, 30);
$districtScore = min(($districts / 50) * 20, 20);
```

### Change Default Pagination
Edit `DashboardController.php` methods:
```php
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;  // Change default
$limit = min($limit, 100);  // Change max
```

### Customize Chart Colors
Edit `analytics.html` chart creation functions - look for `backgroundColor` arrays.

## Troubleshooting

### Charts Not Displaying
- Ensure Chart.js CDN is loaded
- Check browser console for errors
- Verify API is returning data

### No Data Showing
- Check authentication token is valid
- Verify API server is running
- Check database has provinces/districts/branches data
- Review browser Network tab for API errors

### Performance Issues
- Reduce default limit in queries
- Add database indexes on frequently queried fields
- Implement query caching for stats

## Future Enhancements
- Export data to CSV/PDF
- Date range filtering for trends
- Comparison view (province vs province)
- Historical performance graphs
- Alerts for low performers
- Real-time updates via WebSocket
