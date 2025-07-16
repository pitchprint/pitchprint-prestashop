<?php
/**
* 2023 PitchPrint
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PitchPrint to newer
* versions in the future. If you wish to customize PitchPrint for your
* needs please refer to http://pitchprint.com for more information.
*
*  @author    PitchPrint Inc <hello@pitchprint.com>
*  @copyright 2023 PitchPrint Inc
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PitchPrint Inc.
*/
class PaymentModule extends PaymentModuleCore
{
    /**
     * Fetch the content of $template_name inside the folder current_theme/mails/current_iso_lang/ if found, otherwise in mails/current_iso_lang
     *
     * @param string $template_name template name with extension
     * @param int $mail_type Mail::TYPE_HTML or Mail::TYPE_TXT
     * @param array $var list send to smarty
     *
     * @return string
     */
    /*
    * module: pitchprint
    * date: 2016-03-19 01:58:50
    * version: 8
    */
    protected function getEmailTemplateContent($template_name, $mail_type, $var)
    {
        if (!(strpos($template_name, 'order_conf_product_list') === false)) {
            foreach ($var as $k => $v) {
                if (isset($v['customization'])) {
                    foreach ($v['customization'] as $key => $custom) {
                        if (strpos($custom['customization_text'], 'projectId')) {
                            $var[$k]['customization'][$key]['customization_text'] = '';
                        }
                    }
                }
            }
        }

        return parent::getEmailTemplateContent($template_name, $mail_type, $var);
    }
}
