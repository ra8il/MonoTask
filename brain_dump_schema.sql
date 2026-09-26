-- Brain Dump module schema
-- Run this against `demodb` before using brain_dump.php

CREATE TABLE IF NOT EXISTS brain_dump_buckets (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(255) NOT NULL,
    name          VARCHAR(100) NOT NULL,
    accent_color  VARCHAR(20)  DEFAULT '#00bc00',
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS brain_dump_nodes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    bucket_id   INT NOT NULL,
    type        ENUM('heading', 'info') NOT NULL DEFAULT 'info',
    content     VARCHAR(255) NOT NULL,
    pos_x       INT NOT NULL DEFAULT 0,
    pos_y       INT NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bucket_id) REFERENCES brain_dump_buckets(id) ON DELETE CASCADE,
    INDEX (bucket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS brain_dump_connections (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    bucket_id        INT NOT NULL,
    source_node_id   INT NOT NULL,
    target_node_id   INT NOT NULL,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bucket_id) REFERENCES brain_dump_buckets(id) ON DELETE CASCADE,
    FOREIGN KEY (source_node_id) REFERENCES brain_dump_nodes(id) ON DELETE CASCADE,
    FOREIGN KEY (target_node_id) REFERENCES brain_dump_nodes(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_link (source_node_id, target_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
