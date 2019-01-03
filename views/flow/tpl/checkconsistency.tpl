<html>

    <head>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8">
        <meta http-equiv="Cache-Control" content="no-cache">
        <meta http-equiv="Pragma" content="no-cache">
        <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    </head>

<body>
    <style>
        span.state.sok {color:green;}
        span.state.swarning {color:orange;}
        span.state.serror {color:red;}
        span.state.sfatalm {color:red;text-decoration:line-through;}
        span.state.sfatals {color:red;text-decoration:underline;}
        button.fix {position: absolute;top:0; right: 0;}
        .actions i {margin-right:20px;display:inline_blocks;}
        h3 {
            font-size: 14px;
            font-weight: bold;
            margin: 7px 0 10px 0;
            padding-top: 7px;
            border-top: 1px solid #ddd;
        }
        .actions{border-top: 1px solid #ddd;background: #eee;}
        .checks:nth-of-type(even){
            background: #eee;
        }
    </style>
    [{foreach from=$aModules key=ModulId item=ModId}]
        <div class="checks">
            <h2>[{oxmultilang ident="AC_MI_MODULE"}]: [{$ModId.title}]</h2>

            [{include file="items.tpl" title="AC_MI_VERSION" items=$ModId.aVersions}]

            [{include file="items.tpl" title="AC_MI_CONTROLLER" items=$ModId.aControllers}]

            [{include file="items.tpl" title="AC_MI_EXTEND" items=$ModId.aExtended}]

            [{include file="items.tpl" title="AC_MI_FILES" items=$ModId.aFiles}]

            [{if $ModId.aBlocks|@count > 0}]
                <div style="position: relative;">
                    <h3>[{oxmultilang ident="AC_MI_BLOCKS"}]</h3>
                    <table class="box">
                        <tr><th>Active</th><th>Template</th><th>Blockname</th><th>File</th></tr>
                        [{foreach from=$ModId.aBlocks item=i}]
                            <tr>
                                <td style="width:20px;" class="[{if $i.active}]active[{/if}]">[{$i.active}]</td>
                                <td><span class="state [{$i.t_state}]">[{$i.template}]</span></td>
                                <td><span class="state [{$i.b_state}]">[{$i.block}]</span></td>
                                <td>
                                    <div>
                                        <span class="state [{$i.state}]">[{$i.file}]</span>
                                    </div>
                                </td>
                                <td>
                                    [{* no action
                                    <button onclick="module_internals_fix('block','[{$i.id}]')">
                                        [{if $i.active}]
                                            [{oxmultilang ident="AC_MI_DEACTIVATEBTN"}]
                                        [{else}]
                                            [{oxmultilang ident="AC_MI_ACTIVATEBTN"}]
                                        [{/if}]
                                    </button>
                                    *}]
                                </td>
                            </tr>
                        [{/foreach}]
                    </table>
                    <br>
                </div>
            [{/if}]

            [{include file="items.tpl" title="AC_MI_TEMPLATES" items=$ModId.aTemplates}]

            [{include file="items.tpl" title="AC_MI_SETTINGS" items=$ModId.aSettings}]

            [{include file="items.tpl" title="AC_MI_EVENTS" items=$ModId.aEvents}]

            [{if $ModId.aEvents|@count == 0
            && $ModId.aSettings|@count == 0
            && $ModId.aControllers|@count == 0
            && $ModId.aTemplates|@count == 0
            && $ModId.aBlocks|@count == 0
            && $ModId.aFiles|@count == 0
            && $ModId.aExtended|@count == 0
            && $ModId.aVersions|@count == 0
            }]
                -
            [{/if}]
        </div>
    [{/foreach}]

    <div class="actions">
        <b>[{oxmultilang ident="AC_LEGEND"}] : </b>
        <span class="state sok">[{oxmultilang ident="AC_STATE_OK"}]</span> <i>[{oxmultilang ident="AC_STATE_OK_LABEL"}]</i>
        <span class="state swarning">[{oxmultilang ident="AC_STATE_WA"}]</span> <i>[{oxmultilang ident="AC_STATE_WA_LABEL"}]</i>
        <span class="state serror">[{oxmultilang ident="AC_STATE_ER"}]</span> <i>[{oxmultilang ident="AC_STATE_ER_LABEL"}]</i>
        <span class="state sfatalm">[{oxmultilang ident="AC_STATE_FM"}]</span> <i>[{oxmultilang ident="AC_STATE_FM_LABEL"}]</i>
        <span class="state sfatals">[{oxmultilang ident="AC_STATE_FS"}]</span> <i>[{oxmultilang ident="AC_STATE_FS_LABEL"}]</i>
    </div>

</body>
</html>