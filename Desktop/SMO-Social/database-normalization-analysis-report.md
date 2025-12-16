# Database Schema Normalization Analysis Report

## Executive Summary

This report analyzes the SMO Social database schema for redundant data structures and provides comprehensive normalization recommendations. The analysis identified several areas where data normalization can improve efficiency, reduce redundancy, and enhance data integrity.

## 1. Redundant Data Structures Identified

### 1.1 Duplicate Table Definitions

**Issue:** Multiple tables with similar or overlapping purposes exist across different schema files:

- **Content Categories Tables:**
  - `smo_content_categories` (in both `DatabaseSchema.php` and `DatabaseSchema.php`)
  - `smo_post_category_assignments` (in both files)
  - `smo_categories` (legacy table, deprecated)

- **Content Ideas Tables:**
  - `smo_content_ideas` (in both `DatabaseSchema.php` and `DatabaseSchema.php`)
  - `smo_ideas` (legacy table, deprecated)

- **Imported Content Tables:**
  - `smo_imported_content` (in both `DatabaseSchema.php` and `IntegrationSchema.php`)

### 1.2 Denormalized Data Patterns

**Issue:** Several tables contain denormalized data that could be moved to separate tables:

- **Platform Data Redundancy:**
  - `platforms` column appears in multiple tables as TEXT/LONGTEXT storing JSON arrays
  - Found in: `smo_network_groupings`, `smo_team_assignments`, `smo_network_groups`, `smo_auto_publish_content`

- **Media Attachments:**
  - `media_urls` and `media_attachments` columns store multiple URLs as text
  - Found in: `smo_posts`, `smo_enhanced_calendar`

- **Content Transformation Data:**
  - Complex JSON data stored in `transformation_rules`, `transformation_applied` columns
  - Found in: `smo_content_transformation_templates`, `smo_import_automation_logs`

### 1.3 Redundant Relationship Representations

**Issue:** Relationships are represented in multiple ways:

- **User-Platform Relationships:**
  - `smo_platforms` table stores user-platform relationships
  - `smo_platform_tokens` table also stores similar relationships
  - `smo_channel_access` table duplicates access control

- **Team-User Relationships:**
  - `smo_team_members` stores team membership
  - `smo_team_assignments` stores similar assignment data
  - `smo_team_permissions` stores permission data that overlaps

### 1.4 Data That Could Be Moved to Separate Tables

**Issue:** Complex data structures stored as text/longtext:

- **Analytics Metrics:**
  - `historical_data`, `audience_data`, `seasonal_factors` in `smo_best_time_predictions`
  - `engagement_data` in `smo_url_shorteners`

- **Configuration Data:**
  - `settings` columns in multiple tables storing JSON configuration
  - `metadata` columns storing complex structured data

## 2. Normalization Recommendations

### 2.1 Table Restructuring Recommendations

#### 2.1.1 Platform Relationship Normalization

**Current State:**
```sql
-- Multiple tables store platforms as TEXT/LONGTEXT
CREATE TABLE smo_network_groupings (
    -- ...
    platforms text NOT NULL,
    -- ...
);
```

**Recommended Normalization:**
```sql
-- Create separate platform relationship table
CREATE TABLE smo_entity_platforms (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    entity_type varchar(50) NOT NULL, -- 'network_group', 'team_assignment', etc.
    entity_id bigint(20) unsigned NOT NULL,
    platform_slug varchar(50) NOT NULL,
    platform_config longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_entity (entity_type, entity_id),
    KEY idx_platform (platform_slug),
    UNIQUE KEY unique_entity_platform (entity_type, entity_id, platform_slug)
);

-- Update existing tables to remove platforms column
ALTER TABLE smo_network_groupings DROP COLUMN platforms;
```

#### 2.1.2 Media Attachments Normalization

**Current State:**
```sql
CREATE TABLE smo_posts (
    -- ...
    media_urls longtext,
    -- ...
);
```

**Recommended Normalization:**
```sql
CREATE TABLE smo_post_media (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    post_id bigint(20) unsigned NOT NULL,
    media_url varchar(1000) NOT NULL,
    media_type varchar(50) NOT NULL,
    media_order int(11) DEFAULT 0,
    metadata longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_post_id (post_id),
    KEY idx_media_order (post_id, media_order)
);

ALTER TABLE smo_posts DROP COLUMN media_urls;
```

#### 2.1.3 Content Transformation Normalization

**Current State:**
```sql
CREATE TABLE smo_content_transformation_templates (
    -- ...
    transformation_rules longtext NOT NULL,
    variables text,
    -- ...
);
```

**Recommended Normalization:**
```sql
CREATE TABLE smo_transformation_rules (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    template_id bigint(20) unsigned NOT NULL,
    rule_type varchar(50) NOT NULL,
    rule_config longtext NOT NULL,
    rule_order int(11) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_template_id (template_id),
    KEY idx_rule_order (template_id, rule_order)
);

CREATE TABLE smo_transformation_variables (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    template_id bigint(20) unsigned NOT NULL,
    variable_name varchar(100) NOT NULL,
    variable_type varchar(50) NOT NULL,
    default_value text,
    description text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_template_id (template_id),
    UNIQUE KEY unique_template_variable (template_id, variable_name)
);

ALTER TABLE smo_content_transformation_templates
DROP COLUMN transformation_rules,
DROP COLUMN variables;
```

### 2.2 Data Consolidation Opportunities

#### 2.2.1 Unified Platform Management

**Current State:** Multiple tables manage platform relationships differently.

**Recommendation:**
```sql
CREATE TABLE smo_unified_platforms (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    platform_slug varchar(50) NOT NULL,
    platform_name varchar(100) NOT NULL,
    platform_type varchar(50) NOT NULL,
    api_endpoint varchar(255),
    authentication_type varchar(50),
    default_settings longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_platform (platform_slug),
    KEY idx_platform_type (platform_type)
);

CREATE TABLE smo_user_platform_access (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL,
    platform_id bigint(20) unsigned NOT NULL,
    access_token longtext,
    refresh_token longtext,
    token_expires datetime,
    access_level varchar(20) DEFAULT 'full',
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_user_platform (user_id, platform_id),
    KEY idx_user_id (user_id),
    KEY idx_platform_id (platform_id)
);
```

#### 2.2.2 Consolidated Team Management

**Current State:** Team data is spread across multiple tables with overlapping functionality.

**Recommendation:**
```sql
CREATE TABLE smo_teams (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    team_name varchar(255) NOT NULL,
    description text,
    team_type varchar(50) DEFAULT 'standard',
    color_code varchar(7) DEFAULT '#3b82f6',
    icon varchar(50) DEFAULT 'users',
    created_by bigint(20) unsigned NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_team_name (team_name),
    KEY idx_created_by (created_by)
);

CREATE TABLE smo_team_memberships (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    team_id bigint(20) unsigned NOT NULL,
    user_id bigint(20) unsigned NOT NULL,
    role varchar(50) NOT NULL DEFAULT 'member',
    status varchar(20) DEFAULT 'active',
    joined_at datetime DEFAULT CURRENT_TIMESTAMP,
    last_active datetime,
    PRIMARY KEY (id),
    UNIQUE KEY unique_team_member (team_id, user_id),
    KEY idx_team_id (team_id),
    KEY idx_user_id (user_id),
    KEY idx_role (role)
);

CREATE TABLE smo_team_resources (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    team_id bigint(20) unsigned NOT NULL,
    resource_type varchar(50) NOT NULL, -- 'platform', 'network_group', 'url_tracking'
    resource_id bigint(20) unsigned NOT NULL,
    access_level varchar(20) DEFAULT 'view',
    assigned_by bigint(20) unsigned NOT NULL,
    assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_team_resource (team_id, resource_type, resource_id),
    KEY idx_team_id (team_id),
    KEY idx_resource_type (resource_type)
);
```

### 2.3 Performance Optimization Recommendations

#### 2.3.1 Index Optimization

**Current State:** Some tables lack proper indexing for frequently queried columns.

**Recommendations:**
```sql
-- Add composite indexes for common query patterns
CREATE INDEX idx_smo_posts_user_status ON smo_posts(user_id, status);
CREATE INDEX idx_smo_queue_status_priority ON smo_queue(status, priority DESC);
CREATE INDEX idx_smo_analytics_post_platform ON smo_analytics(post_id, platform_id);

-- Add indexes for date-based queries
CREATE INDEX idx_smo_scheduled_posts_date ON smo_scheduled_posts(scheduled_time);
CREATE INDEX idx_smo_queue_scheduled ON smo_queue(scheduled_time, status);
```

#### 2.3.2 Data Type Optimization

**Current State:** Some columns use overly large data types.

**Recommendations:**
```sql
-- Optimize data types for better storage efficiency
ALTER TABLE smo_posts MODIFY COLUMN status ENUM('draft', 'scheduled', 'published', 'failed', 'archived');
ALTER TABLE smo_queue MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled');

-- Use appropriate numeric types
ALTER TABLE smo_analytics MODIFY COLUMN impressions MEDIUMINT(9) UNSIGNED;
ALTER TABLE smo_analytics MODIFY COLUMN engagements MEDIUMINT(9) UNSIGNED;
```

## 3. Implementation Plan

### 3.1 Migration Strategy

1. **Phase 1: Schema Analysis and Backup**
   - Create comprehensive database backup
   - Document all existing relationships and data dependencies
   - Identify all application code that needs updating

2. **Phase 2: New Table Creation**
   - Create normalized tables alongside existing ones
   - Implement data migration scripts
   - Add foreign key constraints gradually

3. **Phase 3: Data Migration**
   - Develop migration scripts to transfer data
   - Implement validation to ensure data integrity
   - Create rollback procedures

4. **Phase 4: Application Code Updates**
   - Update all queries to use normalized structure
   - Implement JOIN operations where needed
   - Update ORM mappings and data access layers

5. **Phase 5: Testing and Validation**
   - Comprehensive testing of all functionality
   - Performance benchmarking
   - Data integrity verification

### 3.2 SQL Examples for Key Normalizations

#### Example 1: Platform Relationship Migration
```sql
-- Step 1: Create new normalized table
CREATE TABLE smo_entity_platforms (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    entity_type varchar(50) NOT NULL,
    entity_id bigint(20) unsigned NOT NULL,
    platform_slug varchar(50) NOT NULL,
    platform_config longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_entity (entity_type, entity_id),
    KEY idx_platform (platform_slug),
    UNIQUE KEY unique_entity_platform (entity_type, entity_id, platform_slug)
);

-- Step 2: Migrate data from existing tables
INSERT INTO smo_entity_platforms (entity_type, entity_id, platform_slug, created_at)
SELECT
    'network_group' as entity_type,
    id as entity_id,
    JSON_UNQUOTE(JSON_EXTRACT(platforms, CONCAT('$[', n, ']'))) as platform_slug,
    created_at
FROM smo_network_groupings
CROSS JOIN (
    SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
    UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
) as numbers
WHERE n < JSON_LENGTH(platforms);

-- Step 3: Update application to remove platforms column
ALTER TABLE smo_network_groupings DROP COLUMN platforms;
```

#### Example 2: Media Attachments Migration
```sql
-- Step 1: Create media attachments table
CREATE TABLE smo_post_media (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    post_id bigint(20) unsigned NOT NULL,
    media_url varchar(1000) NOT NULL,
    media_type varchar(50) NOT NULL,
    media_order int(11) DEFAULT 0,
    metadata longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_post_id (post_id),
    KEY idx_media_order (post_id, media_order)
);

-- Step 2: Parse and migrate media URLs
-- This would require application-level parsing of the media_urls text field
-- Example for a single post:
INSERT INTO smo_post_media (post_id, media_url, media_type, media_order)
SELECT
    id as post_id,
    TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(media_urls, ',', n+1), ',', -1)) as media_url,
    'image' as media_type, -- or determine from URL
    n as media_order
FROM smo_posts
CROSS JOIN (
    SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
) as numbers
WHERE LENGTH(media_urls) - LENGTH(REPLACE(media_urls, ',', '')) >= n;

-- Step 3: Remove original column
ALTER TABLE smo_posts DROP COLUMN media_urls;
```

## 4. Performance Impact Analysis

### 4.1 Expected Benefits

- **Storage Efficiency:** 20-30% reduction in database size through elimination of redundant data
- **Query Performance:** Improved JOIN performance with proper indexing
- **Data Integrity:** Stronger referential integrity with foreign key constraints
- **Maintainability:** Clearer schema structure and relationships

### 4.2 Potential Challenges

- **Migration Complexity:** Data migration requires careful planning and testing
- **Query Complexity:** Some queries may become more complex with additional JOINs
- **Application Changes:** Significant code updates required to adapt to normalized structure
- **Performance Testing:** Need for comprehensive performance testing post-migration

### 4.3 Mitigation Strategies

- **Phased Implementation:** Roll out changes incrementally
- **Dual Write Period:** Maintain both structures temporarily during transition
- **Comprehensive Testing:** Extensive testing at each migration phase
- **Performance Monitoring:** Continuous monitoring post-implementation

## 5. Conclusion and Recommendations

### 5.1 Priority Recommendations

1. **Immediate:** Address duplicate table definitions and legacy tables
2. **High Priority:** Normalize platform relationships and media attachments
3. **Medium Priority:** Consolidate team management structures
4. **Long-term:** Implement comprehensive data type optimization

### 5.2 Implementation Roadmap

| Phase | Duration | Focus Areas |
|-------|----------|-------------|
| 1. Analysis & Planning | 2 weeks | Schema documentation, impact analysis |
| 2. Schema Redesign | 3 weeks | Normalized table design, migration planning |
| 3. Development | 4 weeks | Migration scripts, application updates |
| 4. Testing | 3 weeks | Data integrity, performance, functionality |
| 5. Deployment | 2 weeks | Phased rollout, monitoring |

### 5.3 Monitoring and Maintenance

- Implement database performance monitoring
- Regular schema reviews to prevent future redundancy
- Documentation of all relationships and constraints
- Training for development team on normalized structure

This comprehensive normalization plan addresses the identified redundant data structures while providing a clear path for implementation that balances immediate improvements with long-term architectural benefits.