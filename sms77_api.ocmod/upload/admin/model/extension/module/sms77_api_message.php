<?php

class ModelExtensionModuleSms77ApiMessage extends Model {
    private function _toString($data) {
        if (!is_string($data)) {
            $data = json_encode($data);
        }

        return $data;
    }

    public function addMessage($config) {
        $config = $this->_toString($config);

        $this->db->query("INSERT INTO " . DB_PREFIX . "sms77_api_message SET config = '" . $config . "'");

        $lastId = $this->db->getLastId();

        $this->cache->delete('sms77_api_message');

        return $lastId;
    }

    public function setMessageResponse($id, $response) {
        $response = $this->_toString($response);

        $this->db->query("UPDATE " . DB_PREFIX . "sms77_api_message SET response = '$response' WHERE id = '" . (int)$id . "'");

        $this->cache->delete('sms77_api_message');
    }

    public function deleteMessage($id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "sms77_api_message` WHERE id = '" . (int)$id . "'");

        $this->cache->delete('sms77_api_message');
    }

    public function getMessage($id) {
        $query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "sms77_api_message WHERE id = '" . (int)$id . "'");

        return $query->row;
    }

    public function getMessages($data = []) {
        if ($data) {
            $sql = "SELECT * FROM " . DB_PREFIX . "sms77_api_message";

            if (isset($data['sort']) && in_array($data['sort'], [
                    'id.title',
                    'i.sort_order',
                ])) {
                $sql .= " ORDER BY " . $data['sort'];
            } else {
                $sql .= " ORDER BY id.title";
            }

            if (isset($data['order']) && 'DESC' === $data['order']) {
                $sql .= " DESC";
            } else {
                $sql .= " ASC";
            }

            if (isset($data['start']) || isset($data['limit'])) {
                if ($data['start'] < 0) {
                    $data['start'] = 0;
                }

                if ($data['limit'] < 1) {
                    $data['limit'] = 20;
                }

                $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
            }

            return $this->db->query($sql)->rows;
        }

        $langId = (int)$this->config->get('config_language_id');

        $message = $this->cache->get('sms77_api_message.' . $langId);

        if (!$message) {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "sms77_api_message");

            $message = $query->rows;

            $this->cache->set('sms77_api_message.' . $langId, $message);
        }

        return $message;
    }

    public function getTotalMessages() {
        return $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "sms77_api_message")->row['total'];
    }

    public function install() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "sms77_api_message` (
		  `id` INT(11) NOT NULL AUTO_INCREMENT,
		  `config` TEXT NOT NULL,
          `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `response` TEXT DEFAULT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "sms77_api_message`");
    }
}