<?php

use VersionPress\Utils\UninstallationUtil;

defined('ABSPATH') or die("Direct access not allowed");

function _vp_button($label, $action, $type = "delete", $cssClass = "") {
    echo "<form action='" . admin_url('admin-post.php') . "' method='post' class='$cssClass'>";
    echo "<input type='hidden' name='action' value='$action' />";
    submit_button($label, $type, $action, false, $other_attributes = array("id" => $action) );
    echo "</form>";
}

?>

<div class="wrap vp-deactivation">
    <h2 class="vp-deactivation-header">VersionPress deactivation</h2>

    <div class="error below-h2">
        <p>You are about to deactivate VersionPress. Be aware that:</p>

        <ul>

            <li>
                <span class="icon icon-notification warning-color"></span>
                <span class="vp-highlight-text">Later installations of VersionPress <strong>will not be able to undo or rollback changes</strong> done by this installation.</span> The changes are still technically in the repository if you want to inspect them using e.g. the command line tools etc.
            </li>

            <li>
                <span class="icon icon-checkmark ok-color"></span>
                Deactivation <strong>keeps the Git repository on the server</strong>. You can e.g. download the repository for local inspection.
            </li>

            <li>
                <span class="icon icon-checkmark ok-color"></span>
                You <strong>can reactivate VersionPress again</strong> and the current Git repository will not cause any trouble. The new VersionPress installation will just not be able to undo the old changes as stated above.
            </li>

            <?php
            if (UninstallationUtil::uninstallationShouldRemoveGitRepo()) {
                ?>

                <li>
                    <span class="icon icon-notification warning-color"></span>
                    If you <strong>uninstall</strong> VersionPress later (by clicking the Delete button on the Plugins page) the Git repository will be moved to the <code>wp-content/vpbackups</code> folder. The site will appear unversioned but you can always restore the repository from there should you need to.
                </li>

            <?php
            }
            ?>
        </ul>

        <div class="deactivation-buttons">

            <?php _vp_button("Cancel", "cancel_deactivation", "delete", "cancel-deactivation"); ?>
            <?php _vp_button("Confirm deactivation", "confirm_deactivation", "primary"); ?>

        </div>

    </div>

</div>
