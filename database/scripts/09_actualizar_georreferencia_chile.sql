USE logico_entrega3;

-- Parche para cumplir retroalimentación EV02:
-- incluir dirección completa con comuna, provincia y región en farmacias y motoristas.
-- Adaptado a división político-administrativa de Chile.

ALTER TABLE farmacias
    ADD COLUMN IF NOT EXISTS provincia VARCHAR(80) NULL AFTER comuna,
    ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL AFTER provincia;

ALTER TABLE motoristas
    ADD COLUMN IF NOT EXISTS direccion VARCHAR(180) NULL AFTER apellidos,
    ADD COLUMN IF NOT EXISTS comuna VARCHAR(80) NULL AFTER direccion,
    ADD COLUMN IF NOT EXISTS provincia VARCHAR(80) NULL AFTER comuna,
    ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL AFTER provincia;

UPDATE farmacias SET
    direccion = 'Av. Libertador Bernardo O\'Higgins 1449',
    comuna = 'Santiago',
    provincia = 'Santiago',
    region = 'Región Metropolitana de Santiago'
WHERE codigo = 'F000';

UPDATE farmacias SET
    direccion = 'Av. Santa María 2401',
    comuna = 'Arica',
    provincia = 'Arica',
    region = 'Región de Arica y Parinacota'
WHERE codigo = 'F001';

UPDATE farmacias SET
    direccion = 'Av. Recoleta 455',
    comuna = 'Recoleta',
    provincia = 'Santiago',
    region = 'Región Metropolitana de Santiago'
WHERE codigo = 'F002';

UPDATE farmacias SET
    direccion = 'Av. Colón 980',
    comuna = 'Punta Arenas',
    provincia = 'Magallanes',
    region = 'Región de Magallanes y de la Antártica Chilena'
WHERE codigo = 'F003';

UPDATE motoristas SET
    direccion = 'Pasaje Azapa 1254',
    comuna = 'Arica',
    provincia = 'Arica',
    region = 'Región de Arica y Parinacota'
WHERE rut = '11111111-1';

UPDATE motoristas SET
    direccion = 'Av. México 865',
    comuna = 'Recoleta',
    provincia = 'Santiago',
    region = 'Región Metropolitana de Santiago'
WHERE rut = '12222222-2';

UPDATE motoristas SET
    direccion = 'Calle Manantiales 321',
    comuna = 'Punta Arenas',
    provincia = 'Magallanes',
    region = 'Región de Magallanes y de la Antártica Chilena'
WHERE rut = '13333333-3';

-- Completa valores vacíos en caso de registros creados antes del parche.
UPDATE farmacias
SET provincia = COALESCE(NULLIF(provincia, ''), comuna),
    region = COALESCE(NULLIF(region, ''), 'Región no informada')
WHERE provincia IS NULL OR provincia = '' OR region IS NULL OR region = '';

UPDATE motoristas
SET direccion = COALESCE(NULLIF(direccion, ''), 'Dirección no informada'),
    comuna = COALESCE(NULLIF(comuna, ''), 'Comuna no informada'),
    provincia = COALESCE(NULLIF(provincia, ''), comuna),
    region = COALESCE(NULLIF(region, ''), 'Región no informada')
WHERE direccion IS NULL OR direccion = '' OR comuna IS NULL OR comuna = '' OR provincia IS NULL OR provincia = '' OR region IS NULL OR region = '';

ALTER TABLE farmacias
    MODIFY provincia VARCHAR(80) NOT NULL,
    MODIFY region VARCHAR(120) NOT NULL;

ALTER TABLE motoristas
    MODIFY direccion VARCHAR(180) NOT NULL,
    MODIFY comuna VARCHAR(80) NOT NULL,
    MODIFY provincia VARCHAR(80) NOT NULL,
    MODIFY region VARCHAR(120) NOT NULL;
