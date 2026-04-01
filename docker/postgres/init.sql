-- Otorgar privilegios al usuario appapi para crear bases de datos tenant
-- Necesario para que stancl/tenancy pueda crear app_demo1, app_*, etc.
ALTER USER appapi CREATEDB;
