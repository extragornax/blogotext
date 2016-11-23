<?php
# *** LICENSE ***
# This file is part of BlogoText.
# http://lehollandaisvolant.net/blogotext/
#
# 2006      Frederic Nassar.
# 2010-2016 Timo Van Neerden.
#
# BlogoText is free software.
# You can redistribute it under the terms of the MIT / X11 Licence.
#
# *** LICENSE ***


/**
 * addon as a config ?
 *
 * @return bool
 */
function addon_has_conf($addonName)
{
    $infos = addon_get_infos($addonName);
    if ($infos === false) {
        return false;
    }
    return (isset($infos['config']) && count($infos['config']) > 0);
}

/**
 * get the config of an addon
 *
 * @param string $addonName, the addon name
 * @return array
 */
function addon_get_conf($addonName)
{
    $infos = addon_get_infos($addonName);
    if ($infos === false) {
        return false;
    }
    $saved = array();
    $file_path = BT_ROOT.DIR_ADDONS.'/'.$addonName.'/params.ini';
    if (is_file($file_path) and is_readable($file_path)) {
        $t = parse_ini_file($file_path);
        foreach ($t as $option => $value) {
            $saved[$option] = $value;
        }
    }
    if (isset($infos['config'])) {
        if (!is_array($saved)) {
            return $infos['config'];
        } else {
            foreach ($infos['config'] as $key => $vals) {
                $infos['config'][$key]['value'] = (isset($saved[$key])) ? $saved[$key] : $vals['value'] ;
            }
            return $infos['config'];
        }
    }
    return array();
}

/**
 * get addon informations
 *
 * @param string $addonName, the addon name
 * @return array||false, false if addon not found/loaded...
 */
function addon_get_infos($addonName)
{
    foreach ($GLOBALS['addons'] as $k) {
        if ($k['tag'] == $addonName) {
            return $k;
        }
    }
    return false;
}

/**
 * process (check) the submited config change for an addon
 *
 * todo :
 *   - manage errors
 *
 * @param string $addonName, the addon name
 * @return bool
 */
function addon_edit_params_process($addonName)
{
    $errors = array();
    $addons_status = list_addons();
    $params = addon_get_conf($addonName);
    $datas = array();
    foreach ($params as $key => $param) {
        $datas[$key] = '';
        if ($param['type'] == 'bool') {
            $datas[$key] = (int) (isset($_POST[$key]));
        } else if ($param['type'] == 'int') {
            if (isset($_POST[$key]) && is_numeric($_POST[$key])) {
                $value = (int) $_POST[$key];
                if (isset($param['value_min']) && $value < $param['value_min']) {
                    $errors[$key][] = 'Value is behind limit min.';
                } else if (isset($param['value_max']) && $value > $param['value_max']) {
                    $errors[$key][] = 'Value is reach limit max.';
                } else {
                    $datas[$key] = $value;
                }
            } else {
                // error
                $errors[$key][] = 'No data posted';
            }
        } else if ($param['type'] == 'text') {
            $datas[$key] = htmlentities($_POST[$key], ENT_QUOTES);
        } else if ($param['type'] == 'select') {
            if (isset($param['options'][$_POST[$key]])) {
                $datas[$key] = htmlentities($_POST[$key], ENT_QUOTES);
            } else {
                $errors[$key][] = 'not a valid type';
            }
        } else {
            // error
            $errors[$key][] = 'not a valid type';
        }
    }
    $conf  = '';
    foreach ($datas as $key => $value) {
        $conf .= $key .' = '.((is_numeric($value)) ? $value : '\''.$value.'\'').''."\n";
    }
    return (file_put_contents(BT_ROOT.DIR_ADDONS.'/'.$addonName.'/params.ini', $conf) !== false);
}

/**
 * Get the addon config form
 *
 * @param string $addonName, the addon name
 * @return string, the html form
 */
function addon_edit_params_form($addonName)
{
    // load addons
    $addons_status = list_addons();
    // get info for the addon
    $infos = addon_get_infos($addonName);
    // addon is active ?
    $infos['status'] = $addons_status[$addonName];
    // get addon params
    $params = addon_get_conf($addonName);

    $out = '';
    $out .= '<form id="preferences" method="post" action="?addonName='. $addonName .'" >';
    $out .= '<div role="group" class="pref">'; /* no fieldset because browset can’t style them correctly */
    $out .= '<div class="form-legend"><legend class="legend-user">'.$GLOBALS['lang']['addons_settings_legend'].addon_get_translation($infos['name']).'</legend></div>'."\n";

    // build the config form
    $out .= '<div class="form-lines">'."\n";
    foreach ($params as $key => $param) {
        $out .= '<p>';
        if ($param['type'] == 'bool') {
            $out .= form_checkbox($key, ($param['value'] === true || $param['value'] == 1), $param['label'][ $GLOBALS['lang']['id'] ]);
        } else if ($param['type'] == 'int') {
            $val_min = (isset($param['value_min'])) ? ' min="'.$param['value_min'].'" ' : '' ;
            $val_max = (isset($param['value_max'])) ? ' max="'.$param['value_max'].'" ' : '' ;
            $out .= "\t".'<label for="'.$key.'">'.$param['label'][ $GLOBALS['lang']['id'] ].'</label>'."\n";
            $out .= "\t".'<input type="number" id="'.$key.'" name="'.$key.'" size="30" '. $val_min . $val_max .' value="'.$param['value'].'" class="text" />'."\n";
        } else if ($param['type'] == 'text') {
            $out .= "\t".'<label for="'.$key.'">'.$param['label'][ $GLOBALS['lang']['id'] ].'</label>'."\n";
            $out .= "\t".'<input type="text" id="'.$key.'" name="'.$key.'" size="30" value="'.$param['value'].'" class="text" />'."\n";
        } else if ($param['type'] == 'select') {
            $out .= "\t".'<label for="'.$key.'">'.$param['label'][ $GLOBALS['lang']['id'] ].'</label>'."\n";
            $out .= "\t".'<select id="'.$key.'" name="'.$key.'">'."\n";
            // var_dump( $param['value'] );
            foreach ($param['options'] as $opt_key => $label_lang) {
                $selected = ($opt_key == $param['value']) ? ' selected' : '';
                $out .= "\t\t".'<option value="'. $opt_key .'"'. $selected .'>'. $label_lang[ $GLOBALS['lang']['id'] ] .'</option>';
            }
            $out .= "\t".'</select>'."\n";
        }
        $out .= '</p>';
    }
    $out .= '</div>';
    // submit box
    $out .= '<div class="submit-bttns">'."\n";
    $out .= hidden_input('_verif_envoi', '1');
    $out .= hidden_input('token', new_token());
    $out .= '<input type="hidden" name="addon_action" value="params" />';
    $out .= '<button class="submit button-cancel" type="button" onclick="annuler(\'addons.php\');" >'.$GLOBALS['lang']['annuler'].'</button>'."\n";
    $out .= '<button class="submit button-submit" type="submit" name="enregistrer">'.$GLOBALS['lang']['enregistrer'].'</button>'."\n";
    $out .= '</div>'."\n";
    // END submit box
    $out .= '</ul>'."\n\n";
    $out .= '</div>'."\n";
    $out .= '</div>'."\n";
    $out .= '</form>';
    return $out;
}

/* list all addons */
function list_addons()
{
    $addons = array();
    $path = BT_ROOT.DIR_ADDONS;

    if (is_dir($path)) {
        // get the list of installed addons
        $addons_list = rm_dots_dir(scandir($path));

        // include the addons
        foreach ($addons_list as $addon) {
            $inc = sprintf('%s/%s/%s.php', $path, $addon, $addon);
            $is_enabled = is_file(sprintf('%s/%s/.enabled', $path, $addon));
            if (is_file($inc)) {
                $addons[$addon] = $is_enabled;
                include_once $inc;
            }
        }
    }

    return $addons;
}

function addon_get_translation($info)
{
    if (is_array($info)) {
        return $info[$GLOBALS['lang']['id']];
    }
    return $info;
}

function afficher_liste_modules($tableau, $filtre)
{
    if (!empty($tableau)) {
        $out = '<ul id="modules">'."\n";
        foreach ($tableau as $i => $addon) {
            // addon
            $out .= "\t".'<li>'."\n";
            // addon checkbox activation
            $out .= "\t\t".'<span><input type="checkbox" class="checkbox-toggle" name="module_'.$i.'" id="module_'.$i.'" '.(($addon['status']) ? 'checked' : '').' onchange="activate_mod(this);" /><label for="module_'.$i.'"></label></span>'."\n";

            // addon name
            $out .= "\t\t".'<span><a href="addons.php?addon_id='.$addon['tag'].'">'.addon_get_translation($addon['name']).'</a></span>'."\n";

            // addon version
            $out .= "\t\t".'<span>'.$addon['version'].'</span>'."\n";

            $out .= "\t".'</li>'."\n";

            // other infos and params
            $out .= "\t".'<div>'."\n";

            // addon tag
            if (function_exists('addon_'.$addon['tag'])) {
                $out .= "\t\t".'<p><code title="'.$GLOBALS['lang']['label_code_theme'].'">'.'{addon_'.$addon['tag'].'}'.'</code>'.addon_get_translation($addon['desc']).'</p>'."\n";
            } else {
                $out .= "\t\t".'<p>'.$GLOBALS['lang']['label_no_code_theme'].'</p>';
            }
            $out .= "\t\t".'<p>';

            // addon params
            if (addon_has_conf($addon['tag'])) {
                $out .= '<a href="addon.php?addonName='. $addon['tag'] .'">'.$GLOBALS['lang']['addons_settings_link_title'].'</a>';
                if (!empty($addon['url'])) {
                    $out .= ' | ';
                }
            }

            // author URL
            if (!empty($addon['url'])) {
                $out .= '<a href="'.$addon['url'].'">'.$GLOBALS['lang']['label_owner_url'].'</a>';
            }
            $out .= '</p>'."\n";
            $out .= '</div>'."\n";
        }
        $out .= '</ul>'."\n\n";
    } else {
        $out = info($GLOBALS['lang']['note_no_module']);
    }

    echo $out;
}

// TODO: at the end, put this in "afficher_form_filtre()"
function afficher_form_filtre_modules($filtre)
{
    $ret = '<div id="form-filtre">'."\n";
    $ret .= '<form method="get" action="'.basename($_SERVER['SCRIPT_NAME']).'" onchange="this.submit();">'."\n";
    $ret .= "\n".'<select name="filtre">'."\n" ;
    // TOUS
    $ret .= '<option value="all"'.(($filtre == '') ? ' selected="selected"' : '').'>'.$GLOBALS['lang']['label_all'].'</option>'."\n";
    // ACTIVÉS
    $ret .= '<option value="enabled"'.(($filtre == 'enabled') ? ' selected="selected"' : '').'>'.$GLOBALS['lang']['label_enabled'].'</option>'."\n";
    // DÉSACTIVÉS
    $ret .= '<option value="disabled"'.(($filtre == 'disabled') ? ' selected="selected"' : '').'>'.$GLOBALS['lang']['label_disabled'].'</option>'."\n";
    $ret .= '</select> '."\n\n";
    $ret .= '</form>'."\n";
    $ret .= '</div>'."\n";
    echo $ret;
}

function traiter_form_module($module)
{
    $erreurs = array();
    $path = BT_ROOT.DIR_ADDONS;
    $check_file = sprintf('%s/%s/.enabled', $path, $module['addon_id']);
    $is_enabled = is_file($check_file);
    $new_status = (bool) $module['status'];

    if ($is_enabled != $new_status) {
        if ($new_status) {
            // Addon enabled: we create .enabled
            if (!enable_addon($check_file)) {
                $erreurs[] = sprintf($GLOBALS['lang']['err_addon_enabled'], $module['addon_id']);
            }
        } else {
            // Addon disabled: we delete .enabled
            if (!unlink($check_file)) {
                $erreurs[] = sprintf($GLOBALS['lang']['err_addon_disabled'], $module['addon_id']);
            }
        }
    }

    if (isset($_POST['mod_activer'])) {
        if (empty($erreurs)) {
            die('Success'.new_token());
        } else {
            die('Error'.new_token().implode("\n", $erreurs));
        }
    }

    return $erreurs;
}

function init_post_module()
{
    return array (
        'addon_id' => htmlspecialchars($_POST['addon_id']),
        'status' => (isset($_POST['statut']) and $_POST['statut'] == 'on') ? 1 : 0,
    );
}
