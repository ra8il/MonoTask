CREATE TABLE IF NOT EXISTS brain_dump_buckets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    name VARCHAR(80) NOT NULL,
    accent_color CHAR(7) NOT NULL DEFAULT '#10b981',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_bucket_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS brain_dump_nodes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket_id INT UNSIGNED NOT NULL,
    title VARCHAR(50) NOT NULL DEFAULT '',
    content VARCHAR(250) NOT NULL,
    color ENUM('default','blue','green','amber','rose')
        NOT NULL DEFAULT 'default',
    pos_x INT NOT NULL DEFAULT 60,
    pos_y INT NOT NULL DEFAULT 40,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_nodes_bucket (bucket_id),
    CONSTRAINT fk_nodes_bucket
        FOREIGN KEY (bucket_id)
        REFERENCES brain_dump_buckets(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS brain_dump_connections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket_id INT UNSIGNED NOT NULL,
    source_node_id INT UNSIGNED NOT NULL,
    target_node_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_connections_bucket (bucket_id),
    CONSTRAINT fk_connections_bucket
        FOREIGN KEY (bucket_id)
        REFERENCES brain_dump_buckets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_connections_source
        FOREIGN KEY (source_node_id)
        REFERENCES brain_dump_nodes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_connections_target
        FOREIGN KEY (target_node_id)
        REFERENCES brain_dump_nodes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;