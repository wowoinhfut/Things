<?php
/**
 *
 * User: gui.zheng@husor.com
 * Date: 16/12/5 下午7:15
 */
namespace com\beibei\wowo\persistent;

interface Persistent {

    public function save($sw, $sk, $sv);

    public function get($sw, $sk);

    public function lists($sw);

    public function count($sw);

}