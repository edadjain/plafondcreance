CREATE TABLE IF NOT EXISTS llx_plafondcreance_log (
    rowid       INTEGER       AUTO_INCREMENT PRIMARY KEY,
    fk_soc      INTEGER       NOT NULL,
    encours     DOUBLE(24,8)  NOT NULL DEFAULT 0,
    plafond     DOUBLE(24,8)  NOT NULL DEFAULT 0,
    fk_user     INTEGER       NOT NULL,
    action      VARCHAR(50)   NOT NULL,
    date_action DATETIME      NOT NULL
) ENGINE=InnoDB;
