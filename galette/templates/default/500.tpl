{extends file="public_page.tpl"}

{block name="content"}
    <div class="ui red message">
        <h2 class="ui center aligned header">{_T string="Application error"}</h2>
    </div>
    {if $login->isLogged() and $login->isAdmin() or $GALETTE_MODE eq 'DEV'}
        {function name=render_ex}
    <div class="ui segment">
        <h3>{_T string="Details"}</h3>
        <p>
            <strong>{_T string="Type:"}</strong>
            {get_class($exception)}
        </p>
        <p>
            <strong>{_T string="Code:"}</strong>
            {$exception->getCode()}
        </p>
        <p>
            <strong>{_T string="Message:"}</strong>
            {$exception->getMessage()}
        </p>
        <p>
            <strong>{_T string="File:"}</strong>
            {$exception->getFile()}
        </p>
        <p>
            <strong>{_T string="Line:"}</strong>
            {$exception->getLine()}
        </p>
    </div>
    <div class="ui segment">
        <h3>{_T string="Trace"}</h3>
        <pre>{$exception->getTraceAsString()}</pre>
    </div>
            {if $exception->getPrevious()}
                {call render_ex exception=$exception->getPrevious()}
            {/if}
        {/function}

        {call render_ex exception=$exception}
    {/if}
{/block}
