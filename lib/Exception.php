<?php
/**
 * PHPMailer RFC821 SMTP email transport class.
 * PHP Version 5 7
 *
 * @see       https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
 * @copyright 2019 - 2020 sky
 * @copyright 2012 - 2017 Marcus Bointon
 * @license   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @note      This program is distributed in the hope that it will be useful - WITHOUT
 */

namespace lib;

/**
 * PHPMailer exception handler.
 *
 */
class Exception extends \Exception
{
    /**
     * Prettify error message output.
     *
     * @return string
     */
    public function errorMessage()
    {
        return '<strong>' . htmlspecialchars($this->getMessage()) . "</strong><br />\n";
    }
}
