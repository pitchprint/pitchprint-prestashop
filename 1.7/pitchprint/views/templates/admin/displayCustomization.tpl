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

