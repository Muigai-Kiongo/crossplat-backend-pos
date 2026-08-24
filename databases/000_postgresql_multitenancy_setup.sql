CREATE TABLE tenants (
    id SERIAL PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    package_tier VARCHAR(50) CHECK (package_tier IN ('basic', 'pro', 'enterprise')),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Template for enabling Row-Level Security on tenant-specific tables:
/*
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation_policy ON products
    USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::INTEGER);
*/
