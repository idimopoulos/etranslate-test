CREATE TABLE IF NOT EXISTS translation_request (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source_lang VARCHAR(8) NOT NULL,
  target_lang VARCHAR(8) NOT NULL,
  domain VARCHAR(64) NOT NULL,
  external_reference VARCHAR(200) NULL,
  source_text LONGTEXT NULL,
  notranslate_mode ENUM('wrap','placeholders') NOT NULL DEFAULT 'wrap',
  notranslate_patterns TEXT NULL,
  notranslate_clean TINYINT(1) NOT NULL DEFAULT 1,
  original_text LONGTEXT NOT NULL,
  payload_type ENUM('text','html','txt') NOT NULL,
  sent_payload LONGTEXT NULL,
  callback_url TEXT NULL,
  error_url TEXT NULL,
  http_status SMALLINT NULL,
  raw_response LONGTEXT NULL,
  etranslation_request_id VARCHAR(64) NULL,
  status ENUM('pending','sent','failed','translated','unusable','error') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS translation_callback (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  translation_request_id INT UNSIGNED NOT NULL,
  kind ENUM('success','error') NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  raw_headers LONGTEXT NULL,
  raw_body LONGTEXT NULL,
  request_id VARCHAR(64) NULL,
  target_language VARCHAR(8) NULL,
  external_reference VARCHAR(200) NULL,
  error_code VARCHAR(64) NULL,
  error_message TEXT NULL,
  decoded_result LONGTEXT NULL,
  translated_text LONGTEXT NULL,
  unresolved VARCHAR(255) NULL,
  fixed_text LONGTEXT NULL,
  fixed_unresolved VARCHAR(255) NULL,
  note TEXT NULL,
  CONSTRAINT fk_callback_request FOREIGN KEY (translation_request_id)
    REFERENCES translation_request (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
