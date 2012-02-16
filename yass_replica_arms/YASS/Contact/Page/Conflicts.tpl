{*
  Display a list of conflicts

  @param $conflicts array( {yass_conflict} ), augmented by a "diff" field
*}
{if $conflicts}
   <div class="bold">Conflict Log</div>
   <table>
       <tr class="columnheader">
           <th>{ts}ID{/ts}</th>
           <th>{ts}Field{/ts}</th>
           <th>{ts}Winner{/ts}</th>
           <th>{ts}Loser{/ts}</th>
           <th>{ts}Time{/ts}</th>
        </tr>
       {foreach from=$conflicts item=conflict}
           {if $conflict.winner_entity.entityType}
             {assign var="entityType" value=$conflict.win_entity.entityType}
           {else}
             {assign var="entityType" value=$conflict.lose_entity.entityType}
           {/if}
           <!-- Conflict ID: {$conflict.id} -->
           {foreach from=$conflict.diff key=fieldName item=diff}
               <tr class="{cycle values="odd-row,even-row"}">
                    <td valign="top">{$conflict.id}</td>
                    <td valign="top">{$entityType}:{$fieldName}</td>
                    <td valign="top">{$diff.0}</td>
                    <td valign="top">{$diff.1}</td>
                    <td valign="top">{$conflict.timestamp|date_format:"%Y-%m-%d %H:%M:%Se"|crmDate}</td>
               </tr>
           {/foreach}
         </tr>
       {/foreach}
   </table>
{/if}