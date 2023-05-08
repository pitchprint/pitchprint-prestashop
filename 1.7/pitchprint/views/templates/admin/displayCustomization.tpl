{*
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
*}
<style>
    .pp-90thumb {
        width: 90px;
    }
</style>
{if ($pp_customization.type != 'p')}
    {foreach from=$pp_customization.previews key=k item=v}
        <a class="ppc-ps-img"><img src="{$v}" class="pp-90thumb" ></a>
    {/foreach}
{/if}

<div style="display: inline-block; -webkit-inline-box; vertical-align: top; margin-top:10px;">
    {if ($pp_customization.type == 'u')}
        {foreach from=$pp_customization.files key=k item=v}
            <a target="_blank" href="{$v}" >File {$k+1}</a> &nbsp;&nbsp;&nbsp;
        {/foreach}
    {else}
        <p>{$raw_pp_customization}</p>
    {/if}

</div>

