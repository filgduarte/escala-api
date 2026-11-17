CREATE TABLE IF NOT EXISTS musicians (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(254) NULL,
    birthday DATE NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS instruments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    essential BOOLEAN NULL DEFAULT NULL,
    combinable BOOLEAN NULL DEFAULT NULL,
    exclusive BOOLEAN NULL DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS musician_instruments (
    musician_id INT UNSIGNED NOT NULL,
    instrument_id INT UNSIGNED NOT NULL,
    priority INT NOT NULL DEFAULT 0,
    PRIMARY KEY (musician_id, instrument_id),
    INDEX idx_musician_instruments_instrument (instrument_id),
    CONSTRAINT fk_musician_instruments_musician
        FOREIGN KEY (musician_id) REFERENCES musicians (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_musician_instruments_instrument
        FOREIGN KEY (instrument_id) REFERENCES instruments (id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS availabilities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    musician_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_availabilities_musician_date (musician_id, date),
    INDEX idx_availabilities_date (date),
    CONSTRAINT fk_availabilities_musician
        FOREIGN KEY (musician_id) REFERENCES musicians (id)
        ON DELETE CASCADE
) ENGINE=InnoDB;
