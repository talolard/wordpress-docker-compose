<?php

namespace VersionPress\Initialization;

class ReplacerMethods {

    public function insert( $table, $data, $format = null ) {
        global $versionPressContainer;
        

        $wpdbMirrorBridge = $versionPressContainer->resolve(\VersionPress\DI\VersionPressServices::WPDB_MIRROR_BRIDGE);

        $r = $this->__wp_insert($table, $data, $format);

        $this->vp_backup_fields();
        $wpdbMirrorBridge->insert($table, $data);
        $this->vp_restore_fields();

        return $r;
    }

    public function update( $table, $data, $where, $format = null, $where_format = null ) {
        global $versionPressContainer;
        

        $wpdbMirrorBridge = $versionPressContainer->resolve(\VersionPress\DI\VersionPressServices::WPDB_MIRROR_BRIDGE);

        $r = $this->__wp_update($table, $data, $where, $format, $where_format);

        $this->vp_backup_fields();
        $wpdbMirrorBridge->update($table, $data, $where);
        $this->vp_restore_fields();

        return $r;
    }

    public function delete( $table, $where, $where_format = null ) {
        global $versionPressContainer;
        

        $wpdbMirrorBridge = $versionPressContainer->resolve(\VersionPress\DI\VersionPressServices::WPDB_MIRROR_BRIDGE);

        $r = $this->__wp_delete($table, $where, $where_format);

        $this->vp_backup_fields();
        $wpdbMirrorBridge->delete($table, $where);
        $this->vp_restore_fields();

        return $r;
    }

    private $vp_field_backup = array();

    private function vp_backup_fields() {
        $this->vp_field_backup = array(
            "last_error" => $this->last_error,
            "last_query" => $this->last_query,
            "last_result" => $this->last_result,
            "rows_affected" => $this->rows_affected,
            "num_rows" => $this->num_rows,
            "insert_id" => $this->insert_id,
        );
    }

    private function vp_restore_fields() {
        $this->last_error = $this->vp_field_backup["last_error"];
        $this->last_query = $this->vp_field_backup["last_query"];
        $this->last_result = $this->vp_field_backup["last_result"];
        $this->rows_affected = $this->vp_field_backup["rows_affected"];
        $this->num_rows = $this->vp_field_backup["num_rows"];
        $this->insert_id = $this->vp_field_backup["insert_id"];
    }
}