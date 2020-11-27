<?php
session_start();
ob_start();
$conn_id = false;
if (!array_key_exists('path', $_SESSION) || !is_array($_SESSION['path'])) {
    $_SESSION['path'] = array();
}

if (!$GLOBALS["conn_id"]) {
    getLoginForm();
}

if (isset($_POST) && array_key_exists("host", $_POST)) {
    $_SESSION['path'] = ["/"];
    $_SESSION["host"] = $_POST["host"];
    $_SESSION["name"] = $_POST["name"];
    $_SESSION["password"] = $_POST["password"];
    if (startFTP()) {
        getFileTable("/");
        endFTP();
    }
}

if (array_key_exists("fileToUpload", $_FILES)) {
    $target_file = basename($_FILES["fileToUpload"]["name"]);
    $temp_name = $_FILES["fileToUpload"]["tmp_name"];
    uploadFile($temp_name, $target_file);
}

if (array_key_exists("directory", $_GET)) {
    if (startFTP()) {
        getFileTable($_GET["directory"]);
        endFTP();
    }
}

if (array_key_exists("file", $_GET)) {
    $file = $_GET["file"];
    if (startFTP()) {
        if (!ftp_chdir($GLOBALS["conn_id"], $file)) {
            if ($_GET["action"] == "get") {
                downloadFile($file);
            }
            if ($_GET["action"] == "delete") {
                deleteFile($file);
                getFileTable($_SESSION["path"][count($_SESSION["path"]) - 1]);
            }
        } else {
            array_push($_SESSION["path"], $file);
            getFileTable($_SESSION["path"][count($_SESSION["path"]) - 1]);
        }
        endFTP();
    }
}

if (array_key_exists("up", $_GET)) {
    up();
    if (startFTP()) {
        getFileTable($_SESSION["path"][count($_SESSION["path"]) - 1]);
    }
    endFTP();
}


if (isset($_DELETE) && array_key_exists("file", $_DELETE)) {
    $file = $_DELETE["file"];
    if (startFTP()) {
        if (deleteFile($file)) {
            getFileTable($_SESSION["path"][count($_SESSION["path"]) - 1]);
        }
        endFTP();
    }
}

function startFTP()
{
    $GLOBALS["conn_id"] = ftp_connect($_SESSION["host"]);
    // login with username and password
    $login_result = ftp_login($GLOBALS["conn_id"], $_SESSION["name"], $_SESSION["password"]);
    // check connection
    if ((!$GLOBALS["conn_id"]) || (!$login_result)) {
        echo "FTP connection has failed!";
        return false;
    }
    for ($x = 0; $x < count($_SESSION["path"]); $x++) {
        ftp_chdir($GLOBALS["conn_id"], $_SESSION["path"][$x]);
    }
    return true;
}

function up()
{
    if (count($_SESSION["path"]) > 1) {
        array_pop($_SESSION["path"]);
    }
}

function endFTP()
{
    ftp_close($GLOBALS["conn_id"]);
}

function getFileTable($directory)
{
    ob_clean();
    echo "Current Path: " . ftp_pwd($GLOBALS["conn_id"]) . " ";

    $contents =
        ftp_nlist($GLOBALS["conn_id"], $directory);

    //table header
    echo "<table>";
    echo "<tr><th>Name</th><th colspan=\"2\">Action</th></tr>";

    //upload file / go up row
    echo "<tr>";
    echo "<form action=\"\" method=\"post\" enctype=\"multipart/form-data\">";
    echo "<td>";
    echo "<input type=\"file\" name=\"fileToUpload\" id=\"fileToUpload\">";
    echo "</td>";
    echo "<td>";
    echo "<input type=\"submit\" value=\"UPLOAD\" name=\"submit\">";
    echo "</td>";
    echo "</form>";
    echo "<form method=\"get\" action=\"\">";
    echo "<td>";
    echo "<input type=\"submit\" name=\"submit\"value=\"GO UP\">";
    echo "<input type=\"hidden\" name=\"up\" value=\"up\">";
    echo "</td>";
    echo "</form>";
    echo "</tr>";
    echo "<tr></tr>";


    foreach ($contents as $file) {
        //file/folder row
        echo "<tr>";
        echo "<td>$file</td>";
        echo "<td>";
        echo "<form method=\"get\" action=\"\">";
        echo "<input type=\"submit\" name=\"submit\" value=\"GET\">";
        echo "<input type=\"hidden\" name=\"file\" value=\"$file\">";
        echo "<input type=\"hidden\" name=\"action\" value=\"get\">";
        echo "</form>";
        echo "</td>";
        echo "<td>";
        echo "<form method=\"get\" action=\"\">";
        echo "<input type=\"submit\" name=\"submit\" value=\"DELETE\">";
        echo "<input type=\"hidden\" name=\"file\" value=\"$file\">";
        echo "<input type=\"hidden\" name=\"action\" value=\"delete\">";
        echo "</form>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

function downloadFile($file)
{
    if (ftp_get($GLOBALS["conn_id"], basename($file), basename($file), FTP_BINARY)) {
        if (basename($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename(substr(basename($file), 1)) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize(substr(basename($file), 1)));
            readfile(substr(basename($file), 1));
            unlink(basename($file));
        }
    } else {
        echo "There was a problem\n";
    }
}

function uploadFile($temp_name, $target_file)
{
    if (startFTP()) {
        if (move_uploaded_file($temp_name, $target_file)) {
            if (ftp_put($GLOBALS["conn_id"], $target_file, $target_file, FTP_BINARY)) {
                unlink(basename($target_file));
                getFileTable($_SESSION["path"][count($_SESSION["path"]) - 1]);
            } else {
                echo "There was a problem while uploading $target_file\n";
            }
        }
        endFTP();
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
}

function deleteFile($file)
{
    if (ftp_delete($GLOBALS["conn_id"], $file)) {
        return true;
    }
    echo "File not deleted!";
}

function getLoginForm()
{
    echo "<form method=\"post\" action=\"\">";
    echo "Host: <input type=\"text\" name=\"host\" required value=\"\">";
    echo "<br><br>";
    echo "Name: <input type=\"text\" name=\"name\" required value=\"\">";
    echo "<br><br>";
    echo "Password: <input type=\"text\" name=\"password\" required value=\"\">";
    echo "<br><br>";
    echo "<input type=\"submit\" name=\"submit\" value=\"LOGIN\">";
    echo "</form>";
}
