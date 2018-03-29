<?php

namespace NFePHP\Gtin;

/**
 * Class for validation of GTIN numbers used in NFe
 *
 * @category  NFePHP
 * @package   NFePHP\Gtin
 * @copyright NFePHP Copyright (c) 2008-2018
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-nfe for the canonical source repository
 */

class Gtin
{
    public $stdPrefixCollection;
    public $prefix;
    public $restricted;
    public $region;
    public $checkDigit;
    public $type;
    public $number;
    public $lenght;
    
    public function __construct($gtin)
    {
        $this->stdPrefixCollection = json_decode(file_get_contents(__DIR__.'/prefixcollection.json'));
        if (empty($gtin)) {
            throw new \InvalidArgumentException('Um GTIN deve ser passado para a classe.');
        }
        if (preg_match('/[^0-9]/', $gtin)) {
            throw new \InvalidArgumentException('GTIN deve conter apenas numeros.');
        }
        $this->lenght = (int) strlen($gtin);
        if ($this->lenght != 8 && $this->lenght != 12 && $this->lenght != 13 && $this->lenght != 14) {
            throw new \InvalidArgumentException(
                "Apenas GTIN 8, 12, 13 ou 14 esse numero não atende esses parâmetros."
            );
        }
        $this->number = $gtin;
        $this->prefix = $this->getPrefix($gtin);
        $this->region = $this->getPrefixRegion($this->prefix);
        $this->checkDigit = $this->getCheckDigit($gtin);
        $this->type = $this->getType($gtin);
    }
    
    public static function validate($gtin)
    {
        $g = new Gtin($gtin);
        return $g->isValid();
    }
    
    /**
     * Validate GTIN 8, 12, 13, or 14 with check digit
     * @param string $gtin
     * @return boolean
     */
    public function isValid()
    {
        if ($this->lenght == 14 && substr($this->number, 0, 1) == '0') {
            //first digit of GTIN14 can not be zero
            throw new \InvalidArgumentException(
                "Um GTIN 14 não pode iniciar com numeral ZERO."
            );
        }
        if (!$this->isPrefixValid()) {
            throw new \InvalidArgumentException(
                "O prefixo $this->prefix do GTIN é INVALIDO [$this->region]."
            );
        }
        $dv = (int) substr($this->number, -1);
        if ($dv !== $this->checkDigit) {
            throw new \InvalidArgumentException(
                "O digito verificador é INVALIDO."
            );
        }
        return true;
    }
    
    /**
     * Extract region prefix
     * @param string $gtin
     * @return string
     */
    protected function getPrefix($gtin)
    {
        $type = $this->getType($gtin);
        switch ($type) {
            case 14: //begins with number not zero
                return substr($gtin, 1, 3);
                break;
            default:
                return substr($gtin, 0, 3);
        }
    }
    
    /**
     * Identify GTIN type GTIN 8,12,13,14 or NONE
     * @param string $gtin
     * @return int
     */
    protected function getType($gtin)
    {
        $gtinnorm = str_pad($gtin, 14, '0', STR_PAD_LEFT);
        if (substr($gtinnorm, 0, 6) == '000000') {
            //GTIN 8
            return 8;
        } elseif (substr($gtinnorm, 0, 2) == '00') {
            //GTIN 12
            return 12;
        } elseif (substr($gtinnorm, 0, 1) == '0') {
            //GTIN 13
            return 13;
        } elseif (substr($gtinnorm, 0, 1) != '0') {
            //GTIN 14
            return 14;
        }
        return 0;
    }
    
    /**
     * Validate prefix region
     * @param string $prefix
     * @return boolean
     */
    protected function isPrefixValid()
    {
        return !$this->restricted;
    }
    
    /**
     * Recover region from prefix code
     * @param string $prefix
     * @return string
     */
    protected function getPrefixRegion($prefix)
    {
        $pf = (int) $prefix;
        foreach ($this->stdPrefixCollection as $std) {
            $nI = (int) $std->nIni;
            $nF = (int) $std->nFim;
            $this->restricted = (boolean) $std->restricted;
            $region = $std->region;
            if ($pf >= $nI && $pf <= $nF) {
                return $region;
            }
        }
        return "Not Found";
    }
    
    /**
     * Calculate check digit from GTIN 8, 12, 13 or 14
     * @param string $gtin
     * @return integer
     */
    public function getCheckDigit($gtin)
    {
        $len = (int) strlen($gtin);
        $gtin = substr($gtin, 0, $len-1);
        $gtin = str_pad($gtin, '15', '0', STR_PAD_LEFT);
        $total = 0;
        for ($pos=0; $pos<15; $pos++) {
            $total += ((($pos+1) % 2) * 2 + 1) * $gtin[$pos];
        }
        $dv = 10 - ($total % 10);
        return (int) $dv;
    }
}
