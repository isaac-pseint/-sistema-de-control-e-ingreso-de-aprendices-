USE control_aprendices;

ALTER TABLE ficha
    ADD CONSTRAINT uq_ficha_codigo UNIQUE (codigo);

ALTER TABLE programa
    ADD CONSTRAINT uq_programa_nombre UNIQUE (nombre);
