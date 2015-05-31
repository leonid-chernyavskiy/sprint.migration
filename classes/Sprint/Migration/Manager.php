<?php

namespace Sprint\Migration;

use Sprint\Migration\Exceptions\Restart;

class Manager
{

    private $options = array();
    private $restarts = array();

    public function __construct() {

        if ($file = $this->getConfigFile()){
            $this->options = include $file;
        }

         Db::createTablesIfNotExists();
    }

    public function getVersions() {
        return $this->findVersions('asc');
    }

    public function getVersionsSummary(){
        $versions = $this->findVersions('asc');

        $summ = array(
            'is_new' => 0,
            'is_success' => 0,
            'is_404' => 0,
        );

        foreach ($versions as $item) {
            $type = $item['type'];
            $summ[$type]++;
        }

        return $summ;
    }

    public function getVersionsFor($action = 'up') {
        $action = ($action == 'up') ? 'up' : 'down';
        $desc = ($action == 'up') ? 'asc' : 'desc';
        $type = ($action == 'up') ? 'is_new' : 'is_success';

        $result = array();
        $versions = $this->findVersions($desc);
        foreach ($versions as $item) {
            if ($item['type'] == $type) {
                $result[] = $item['version'];
            }
        }
        return $result;
    }

    public function getOnceVersionFor($action = 'up') {
        $action = ($action == 'up') ? 'up' : 'down';
        $desc = ($action == 'up') ? 'asc' : 'desc';
        $type = ($action == 'up') ? 'is_new' : 'is_success';

        $versions = $this->findVersions($desc);
        foreach ($versions as $item) {
            if ($item['type'] == $type) {
                return $item['version'];
            }
        }
        return false;
    }

    public function getVersionDescription($versionName) {
        $version = $this->initVersion($versionName);
        return ($version) ? $version->getDescription() : '';
    }

    public function executeVersion($name, $action = 'up', $params = array()) {
        $action = ($action && $action == 'up') ? 'up' : 'down';

        $version = $this->findVersionByName($name);
        if (!$version) {
            return false;
        }

        if ($action == 'up' && $version['type'] == 'is_new') {
            return $this->doVersionUp($name, $params);
        }

        if ($action == 'down' && $version['type'] == 'is_success') {
            return $this->doVersionDown($name, $params);
        }

        return false;
    }

    public function needRestart($name){
        return (isset($this->restarts[$name])) ? 1 : 0;
    }

    public function getRestartParams($name){
        return $this->restarts[$name];
    }

    public function createVersionFile($description = '') {
        $description = preg_replace("/\r\n|\r|\n/", '<br/>', $description);
        $description = strip_tags($description);
        $description = addslashes($description);

        $originTz = date_default_timezone_get();
        date_default_timezone_set('Europe/Moscow');
        $version = 'Version' . date('YmdHis');
        date_default_timezone_set($originTz);

        $str = $this->renderVersionFile(array(
            'version' => $version,
            'description' => $description,
        ));
        $file = $this->getVersionFile($version);
        file_put_contents($file, $str);
        return is_file($file) ? $version : false;
    }

    protected function doVersionUp($name, $params = array()) {
        if ($version = $this->initVersion($name)) {
            try {

                unset($this->restarts[$name]);

                $version->setParams($params);

                $ok = $version->up();

                if ($ok !== false) {
                    $this->addRecord($name);
                    return true;
                }

                Out::outError('%s error', $name);

            } catch (Restart $e){
                $this->restarts[$name] = $version->getParams();

            } catch (\Exception $e) {
                Out::outError('%s error: %s', $name, $e->getMessage());
            }
        }
        return false;
    }

    protected function doVersionDown($name, $params = array()) {
        if ($version = $this->initVersion($name)) {
            try {

                unset($this->restarts[$name]);

                $version->setParams($params);

                $ok = $version->down();

                if ($ok !== false) {
                    $this->removeRecord($name);
                    return true;
                }

                Out::outError('%s error', $name);
            } catch (Restart $e){
                $this->restarts[$name] = $version->getParams();

            } catch (\Exception $e) {
                Out::outError('%s error: %s', $name, $e->getMessage());
            }
        }
        return false;
    }


    protected function findVersionByName($name) {
        if (!$this->checkName($name)){
            return false;
        }

        $record = Db::findByName($name)->Fetch();
        $file = $this->getVersionFile($name);

        $isRecord = !empty($record);
        $isFile = file_exists($file);

        if (!$isRecord && !$isFile){
            return false;
        }

        if ($isRecord && $isFile) {
            $type = 'is_success';
        } elseif (!$isRecord && $isFile) {
            $type = 'is_new';
        } else {
            $type = 'is_404';
        }

        return array(
            'type' => $type,
            'version' => $name,
        );
    }

    protected function findVersions($sort = 'asc') {
        $records = $this->getVersionRecords();
        $files = $this->getVersionFiles();

        $merge = array_merge($records, $files);
        $merge = array_unique($merge);

        if ($sort && $sort == 'asc') {
            sort($merge);
        } else {
            rsort($merge);
        }

        $result = array();
        foreach ($merge as $val) {

            $isRecord = in_array($val, $records);
            $isFile = in_array($val, $files);

            if ($isRecord && $isFile) {
                $type = 'is_success';
            } elseif (!$isRecord && $isFile) {
                $type = 'is_new';
            } else {
                $type = 'is_404';
            }

            $aItem = array(
                'type' => $type,
                'version' => $val,
            );

            $result[] = $aItem;
        }

        return $result;
    }

    protected function getVersionFiles() {
        $directory = new \DirectoryIterator($this->getMigrationDir());
        $files = array();
        /* @var $item \SplFileInfo */
        foreach ($directory as $item) {
            $fileName = pathinfo($item->getPathname(), PATHINFO_FILENAME);
            if ($this->checkName($fileName)) {
                $files[] = $fileName;
            }
        }

        return $files;
    }

    protected function getVersionRecords() {
        $dbResult = Db::findAll();

        $records = array();
        while ($aItem = $dbResult->Fetch()) {
            if ($this->checkName($aItem['version'])) {
                $records[] = $aItem['version'];
            }

        }
        return $records;
    }

    protected function addRecord($versionName) {
        if ($this->checkName($versionName)) {
            return Db::addRecord($versionName);
        }
        return false;
    }

    protected function removeRecord($versionName) {
        if ($this->checkName($versionName)) {
            return Db::removeRecord($versionName);
        }
        return false;
    }

    /* @return Version */
    protected function initVersion($versionName) {
        $file = false;
        if ($this->checkName($versionName)) {
            $file = $this->getVersionFile($versionName);
        }

        if (!$file || !file_exists($file)) {
            return false;
        }

        include_once $file;

        $class = 'Sprint\Migration\\' . $versionName;
        if (!class_exists($class)) {
            return false;
        }

        $obj = new $class;
        return $obj;
    }

    protected function getVersionFile($versionName) {
        return $this->getMigrationDir() . '/'.$versionName . '.php';
    }

    protected function checkName($versionName) {
        return preg_match('/^Version\d+$/i', $versionName);
    }

    protected function renderVersionFile($vars = array()) {
        if (is_array($vars)) {
            extract($vars, EXTR_SKIP);
        }

        ob_start();

        include($this->getVersionTemplateFile());

        $html = ob_get_clean();

        return $html;
    }

    protected function getConfigFile(){
        $file = Utils::getPhpInterfaceDir() . '/migrations.cfg.php';
        return is_file($file) ? $file : false;
    }


    protected function getMigrationDir(){
        if (!empty($this->options['migration_dir']) && is_dir(Utils::getDocRoot() . $this->options['migration_dir'])){
            return Utils::getDocRoot() . $this->options['migration_dir'];
        }

        $dir = Utils::getPhpInterfaceDir() . '/migrations';
        if (!is_dir($dir)){
            mkdir($dir , BX_DIR_PERMISSIONS);
        }
        return $dir;
    }

    protected function getVersionTemplateFile(){
        if (!empty($this->options['migration_template']) && is_file(Utils::getDocRoot() . $this->options['migration_template'])){
            return Utils::getDocRoot() . $this->options['migration_template'];
        } else {
            return Utils::getModuleDir() . '/templates/version.php';
        }
    }




}
