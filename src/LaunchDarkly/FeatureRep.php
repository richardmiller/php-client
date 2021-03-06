<?php
namespace LaunchDarkly;

/**
 * @internal
 */
class FeatureRep {
    protected static $LONG_SCALE = 0xFFFFFFFFFFFFFFF;

    protected $_name = null;
    protected $_key = null;
    protected $_salt = null;
    protected $_on = false;

    /** @var Variation[] */
    protected $_variations = array();

    public function __construct($name, $key, $salt, $on = true, $variations = array()) {
        $this->_name = $name;
        $this->_key = $key;
        $this->_salt = $salt;
        $this->_on = $on;
        $this->_variations = $variations;
    }

    /**
     * @param $user LDUser
     * @return mixed
     */
    public function evaluate($user) {
        if (!$this->_on || !$user) {
            return null;
        }

        $param = $this->_get_param($user);
        if (is_null($param)) {
            return null;
        }
        else {
            foreach ($this->_variations as $variation) {
                if ($variation->matchUser($user)) {
                    return $variation->getValue();
                }
            }

            foreach ($this->_variations as $variation) {
                if ($variation->matchTarget($user)) {
                    return $variation->getValue();
                }
            }

            $sum = 0.0;
            foreach ($this->_variations as $variation) {
                $sum += $variation->getWeight() / 100.0;

                if ($param < $sum) {
                    return $variation->getValue();
                }
            }
        }

        return null;
    }

    /**
     * @param $user LDUser
     * @return float|null
     */
    private function _get_param($user) {
        $id_hash = null;
        $hash = null;

        if ($user->getKey()) {
            $id_hash = $user->getKey();
        }
        else {
            return null;
        }

        if ($user->getSecondary()) {
            $id_hash .= "." . $user->getSecondary();
        }

        $hash = substr(sha1($this->_key . "." . $this->_salt . "." . $id_hash), 0, 15);
        $longVal = base_convert($hash, 16, 10);
        $result = $longVal / self::$LONG_SCALE;

        return $result;
    }
}
