<?php

namespace wulaphp\auth;
/**
 * Acl资源管理器.
 *
 * @package wulaphp\auth
 */
class AclResourceManager {

    private $root;

    private function __construct() {
        $this->root = new AclResource ('/');
    }

    /**
     *
     * @param string      $id
     * @param string      $name
     * @param string|null $defaultOp
     *
     * @return AclResource
     */
    public function getResource(string $id = '', string $name = '', ?string $defaultOp = null): AclResource {
        if (empty ($id) || $id == '/') {
            return $this->root;
        } else {
            $ids  = explode('/', $id);
            $node = $this->root;
            $path = [];
            while (($id = array_shift($ids)) != null) {
                $path [] = $id;
                $node    = $node->getNode($id, implode('/', $path));
            }
            if (!empty ($name)) {
                $node->setName($name);
            }
            if ($defaultOp) {
                $node->addOperate($defaultOp, '', '', true);
            }

            return $node;
        }
    }

    /**
     * 取AclResourceManager实例.
     *
     * @param string $type
     *
     * @return AclResourceManager
     */
    public static function getInstance(string $type = 'default'): AclResourceManager {
        static $aclm = [];
        if (!isset($aclm[ $type ])) {
            $manager = new AclResourceManager();
            try {
                fire('rbac\init' . ucfirst($type) . 'Manager', $manager);
            } catch (\Exception $e) {
            }
            $aclm[ $type ] = $manager;
        }

        return $aclm[ $type ];
    }
}