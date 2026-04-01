-- Otorgar privilegios al usuario appapi para crear bases de datos tenant
-- Necesario para que stancl/tenancy pueda crear app_demo1, app_*, etc.
GRANT ALL PRIVILEGES ON `app_%`.* TO 'appapi'@'%';
FLUSH PRIVILEGES;
