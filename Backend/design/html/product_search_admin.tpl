{$meta_title = $btr->sviat_product_discovery_search_title|escape scope=global}

<div class="main_header">
    <div class="main_header__item">
        <div class="main_header__inner">
            <div class="box_heading heading_page">{$btr->sviat_product_discovery_search_title|escape}</div>
        </div>
    </div>
</div>

{if $message_success}
    <div class="row">
        <div class="col-lg-12">
            <div class="alert alert--success">
                <div class="alert__content">
                    <div class="alert__title">{$btr->general_settings_saved|escape}</div>
                </div>
            </div>
        </div>
    </div>
{/if}

<form method="post" id="fn_product_search_admin_form" class="fn_form_list">
    <input type="hidden" name="session_id" value="{$smarty.session.id}">
    <div class="row">
        <div class="col-lg-7 col-md-7">
            <div class="boxed">
                <div class="heading_box">{$btr->sviat__product_search__general_settings|escape}</div>

                <div class="form-group">
                    <div class="activity_of_switch activity_of_switch--left">
                        <div class="activity_of_switch_item">
                            <div class="okay_switch clearfix">
                                <label class="switch_label">{$btr->sviat__product_search__enable_live_search|escape}</label>
                                <label class="switch switch-default">
                                    <input class="switch-input" name="enabled" value="1" type="checkbox" {if $product_search_enabled}checked{/if}>
                                    <span class="switch-label"></span>
                                    <span class="switch-handle"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-4 col-md-4">
                        <div class="form-group">
                            <label class="heading_label">{$btr->sviat__product_search__min_query_length|escape}</label>
                            <input class="form-control" type="number" min="1" max="10" name="min_query_length" value="{$product_search_min_query_length|escape}">
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4">
                        <div class="form-group">
                            <label class="heading_label">{$btr->sviat__product_search__suggestion_limit|escape}</label>
                            <input class="form-control" type="number" min="1" max="30" name="suggestion_limit" value="{$product_search_suggestion_limit|escape}">
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4">
                        <div class="form-group">
                            <label class="heading_label">{$btr->sviat__product_search__name_preview_length|escape}</label>
                            <input class="form-control" type="number" min="40" max="255" name="name_preview_length" value="{$product_search_name_preview_length|escape}">
                        </div>
                    </div>
                </div>

                <div class="heading_box mt-3">{$btr->sviat__product_search__translit_section|escape}</div>
                <div class="boxed_notice mb-2">
                    {$btr->sviat__product_search__translit_notice|escape}
                </div>

                <div class="permission_block">
                    <div class="permission_boxes row">
                        <div class="col-xl-12 col-lg-12 col-md-12">
                            <div class="permission_box permission_box--long">
                                <div class="heading_label">
                                    <span>{$btr->sviat__product_search__translit_enabled|escape}</span>
                                    <i class="fn_tooltips" title="{$btr->sviat__product_search__translit_enabled_tooltip|escape}">
                                        {include file="svg_icon.tpl" svgId="icon_tooltips"}
                                    </i>
                                </div>
                                <label class="switch switch-default">
                                    <input class="switch-input" name="translit_enabled" value="1" type="checkbox" {if $product_search_translit_enabled}checked{/if}>
                                    <span class="switch-label"></span>
                                    <span class="switch-handle"></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-xl-12 col-lg-12 col-md-12">
                            <div class="permission_box permission_box--long">
                                <div class="heading_label">
                                    <span>{$btr->sviat__product_search__translit_phonetic_latin_cyr|escape}</span>
                                    <i class="fn_tooltips" title="{$btr->sviat__product_search__translit_phonetic_latin_cyr_tooltip|escape}">
                                        {include file="svg_icon.tpl" svgId="icon_tooltips"}
                                    </i>
                                </div>
                                <label class="switch switch-default">
                                    <input class="switch-input" name="translit_phonetic_latin_cyr" value="1" type="checkbox" {if $product_search_translit_phonetic_latin_cyr}checked{/if}>
                                    <span class="switch-label"></span>
                                    <span class="switch-handle"></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-xl-12 col-lg-12 col-md-12">
                            <div class="permission_box permission_box--long">
                                <div class="heading_label">
                                    <span>{$btr->sviat__product_search__translit_phonetic_cyr_latin|escape}</span>
                                    <i class="fn_tooltips" title="{$btr->sviat__product_search__translit_phonetic_cyr_latin_tooltip|escape}">
                                        {include file="svg_icon.tpl" svgId="icon_tooltips"}
                                    </i>
                                </div>
                                <label class="switch switch-default">
                                    <input class="switch-input" name="translit_phonetic_cyr_latin" value="1" type="checkbox" {if $product_search_translit_phonetic_cyr_latin}checked{/if}>
                                    <span class="switch-label"></span>
                                    <span class="switch-handle"></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-xl-12 col-lg-12 col-md-12">
                            <div class="permission_box permission_box--long">
                                <div class="heading_label">
                                    <span>{$btr->sviat__product_search__translit_layout_latin_cyr|escape}</span>
                                    <i class="fn_tooltips" title="{$btr->sviat__product_search__translit_layout_latin_cyr_tooltip|escape}">
                                        {include file="svg_icon.tpl" svgId="icon_tooltips"}
                                    </i>
                                </div>
                                <label class="switch switch-default">
                                    <input class="switch-input" name="translit_layout_latin_cyr" value="1" type="checkbox" {if $product_search_translit_layout_latin_cyr}checked{/if}>
                                    <span class="switch-label"></span>
                                    <span class="switch-handle"></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-xl-12 col-lg-12 col-md-12">
                            <div class="permission_box permission_box--long">
                                <div class="heading_label">
                                    <span>{$btr->sviat__product_search__translit_layout_cyr_latin|escape}</span>
                                    <i class="fn_tooltips" title="{$btr->sviat__product_search__translit_layout_cyr_latin_tooltip|escape}">
                                        {include file="svg_icon.tpl" svgId="icon_tooltips"}
                                    </i>
                                </div>
                                <label class="switch switch-default">
                                    <input class="switch-input" name="translit_layout_cyr_latin" value="1" type="checkbox" {if $product_search_translit_layout_cyr_latin}checked{/if}>
                                    <span class="switch-label"></span>
                                    <span class="switch-handle"></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-xl-12 col-lg-12 col-md-12">
                            <div class="permission_box permission_box--long">
                                <div class="heading_label">
                                    <span>{$btr->sviat__product_search__translit_shift_commas|escape}</span>
                                    <i class="fn_tooltips" title="{$btr->sviat__product_search__translit_shift_commas_tooltip|escape}">
                                        {include file="svg_icon.tpl" svgId="icon_tooltips"}
                                    </i>
                                </div>
                                <label class="switch switch-default">
                                    <input class="switch-input" name="translit_shift_commas" value="1" type="checkbox" {if $product_search_translit_shift_commas}checked{/if}>
                                    <span class="switch-label"></span>
                                    <span class="switch-handle"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {if $product_search_redis_available}
                <div class="heading_box mt-3">{$btr->sviat__product_search__cache_section|escape}</div>

                <div class="form-group">
                    <div class="activity_of_switch activity_of_switch--left">
                        <div class="activity_of_switch_item">
                            <div class="okay_switch clearfix">
                                <label class="switch_label">{$btr->sviat__product_search__redis_cache_enabled|escape}</label>
                                <label class="switch switch-default">
                                    <input class="switch-input" name="redis_cache_enabled" value="1" type="checkbox" {if $product_search_redis_cache_enabled}checked{/if}>
                                    <span class="switch-label"></span>
                                    <span class="switch-handle"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="heading_label">{$btr->sviat__product_search__cache_ttl|escape}</label>
                    <input class="form-control" type="number" min="0" max="86400" name="cache_ttl" value="{$product_search_cache_ttl|escape}">
                    <div class="boxed_notice">
                        {$btr->sviat__product_search__cache_ttl_hint|escape}
                    </div>
                </div>

                {if !$product_search_redis_enabled}
                    <div class="alert alert--warning">
                        <div class="alert__content">
                            <div class="alert__title">{$btr->sviat__product_search__redis_disabled_title|escape}</div>
                            <p>{$btr->sviat__product_search__redis_disabled_lead|escape}</p>
                        </div>
                    </div>
                {/if}
                {/if}
                <button type="submit" class="btn btn_blue mt-2">
                    {$btr->general_apply|escape}
                </button>
            </div>
        </div>

        <div class="col-lg-5 col-md-5">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->sviat__product_search__popular_queries_heading|escape}
                </div>
                <div class="toggle_body_wrap on fn_card">
                    <div class="okay_list">
                        <div class="okay_list_head">
                            <div class="okay_list_boding okay_list_drag"></div>
                            <div class="okay_list_heading okay_list_ps_popular_phrase">{$btr->sviat__product_search__popular_phrase|escape}</div>
                            <div class="okay_list_heading okay_list_ps_popular_close"></div>
                        </div>
                        <div id="sortable_ps_popular" class="okay_list_body sortable">
                            {foreach $product_search_popular_queries as $p}
                                <div class="fn_row okay_list_body_item fn_sort_item">
                                    <div class="okay_list_row">
                                        <input type="hidden" name="ps_popular_positions[{$p->id}]" value="{$p->position|escape}">
                                        <input type="hidden" name="ps_popular_id[]" value="{$p->id|escape}">
                                        <div class="okay_list_boding okay_list_drag move_zone">
                                            {include file='svg_icon.tpl' svgId='drag_vertical'}
                                        </div>
                                        <div class="okay_list_boding okay_list_ps_popular_phrase">
                                            <input type="text" name="ps_popular_phrase[]" class="form-control" value="{$p->phrase|escape}" placeholder="{$btr->sviat__product_search__popular_phrase|escape}">
                                        </div>
                                        <div class="okay_list_boding okay_list_ps_popular_close">
                                            <input type="checkbox" name="delete_ps_popular[]" value="{$p->id|escape}" class="hidden_check_1" id="ps_pop_del_{$p->id}">
                                            <button data-hint="{$btr->general_delete|escape}" type="button"
                                                class="btn_close fn_ps_popular_delete_btn hint-bottom-right-t-info-s-small-mobile hint-anim"
                                                data-toggle="modal" data-target="#ps_popular_delete_modal" data-item-id="{$p->id|escape}">
                                                {include file='svg_icon.tpl' svgId='trash'}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            {/foreach}
                            <div class="okay_list_body_item fn_ps_popular_new">
                                <div class="okay_list_row">
                                    <input type="hidden" name="ps_popular_id[]" value="0">
                                    <div class="okay_list_boding okay_list_drag"></div>
                                    <div class="okay_list_boding okay_list_ps_popular_phrase">
                                        <input type="text" name="ps_popular_phrase[]" class="form-control" value="" placeholder="{$btr->sviat__product_search__popular_new_placeholder|escape}">
                                    </div>
                                    <div class="okay_list_boding okay_list_ps_popular_close"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn_blue mt-2">
                        {$btr->general_apply|escape}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<div id="ps_popular_delete_modal" class="modal fade" role="document">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="card-header">
                <div class="heading_modal">{$btr->index_confirm|escape}</div>
            </div>
            <div class="modal-body">
                <button type="button" class="btn btn_small btn_blue fn_ps_popular_confirm_delete mx-h">
                    {include file='svg_icon.tpl' svgId='checked'}
                    <span>{$btr->index_yes|escape}</span>
                </button>
                <button type="button" class="btn btn_small btn-danger mx-h" data-dismiss="modal">
                    {include file='svg_icon.tpl' svgId='delete'}
                    <span>{$btr->index_no|escape}</span>
                </button>
            </div>
        </div>
    </div>
</div>

{literal}
<script>
$(function () {
    var psPopDeleteBtn = null;
    $('#fn_product_search_admin_form').on('submit', function () {
        $('#sortable_ps_popular .fn_sort_item').each(function (index) {
            $(this).find('input[name^="ps_popular_positions"]').val(index + 1);
        });
    });
    $(document).on('click', '.fn_ps_popular_delete_btn', function () {
        psPopDeleteBtn = $(this);
    });
    $(document).on('click', '.fn_ps_popular_confirm_delete', function () {
        if (psPopDeleteBtn && psPopDeleteBtn.length) {
            var $row = psPopDeleteBtn.closest('.fn_row');
            $row.find('input[name="delete_ps_popular[]"]').prop('checked', true);
            psPopDeleteBtn.closest('form').submit();
        }
        $('#ps_popular_delete_modal').modal('hide');
        psPopDeleteBtn = null;
    });
});
</script>
{/literal}
