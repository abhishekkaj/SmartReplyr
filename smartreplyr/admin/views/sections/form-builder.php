<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
// Default core fields if not yet saved
$default_fields = array(
    array('key'=>'name',   'label'=>'Full Name',       'type'=>'text',   'placeholder'=>'John Doe',           'required'=>true,  'enabled'=>true, 'core'=>true),
    array('key'=>'email',  'label'=>'Email Address',   'type'=>'email',  'placeholder'=>'john@example.com',   'required'=>true,  'enabled'=>true, 'core'=>true),
    array('key'=>'phone',  'label'=>'Phone Number',    'type'=>'tel',    'placeholder'=>'Your mobile number', 'required'=>true,  'enabled'=>true, 'core'=>true),
    array('key'=>'course', 'label'=>'Course Interest', 'type'=>'select', 'placeholder'=>'',                   'required'=>false, 'enabled'=>true, 'core'=>true),
);

$raw = $settings['form_fields'] ?? '';
$form_fields = ! empty( $raw ) ? json_decode( $raw, true ) : array();
if ( empty( $form_fields ) || ! is_array( $form_fields ) ) {
    $form_fields = $default_fields;
}
?>

<h2>Form Builder</h2>
<p class="description">Customize the lead capture form. Add, remove, reorder, and configure fields. Core fields (Name, Email, Phone) are always required.</p>

<style>
.sr-form-builder { margin-top: 20px; }
.sr-field-row { display: flex; align-items: center; gap: 10px; background: #f9fafb; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 16px; margin-bottom: 10px; cursor: grab; position: relative; }
.sr-field-row:active { cursor: grabbing; }
.sr-drag-handle { font-size: 18px; color: #94a3b8; cursor: grab; padding: 0 4px; }
.sr-field-row input[type="text"], .sr-field-row select { padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: white; }
.sr-field-row input[type="text"] { width: 160px; }
.sr-field-row label { font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 2px; }
.sr-field-cell { display: flex; flex-direction: column; }
.sr-field-badge { font-size: 10px; background: #e0e7ff; color: #4f46e5; border-radius: 20px; padding: 2px 8px; font-weight: 600; }
.sr-field-badge.core { background: #fef3c7; color: #92400e; }
.sr-field-required-toggle { display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer; }
.sr-remove-field { background: #fee2e2; color: #dc2626; border: none; border-radius: 8px; padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: 600; margin-left: auto; }
.sr-remove-field:hover { background: #fca5a5; }
.sr-remove-field:disabled { background: #f3f4f6; color: #94a3b8; cursor: not-allowed; }
.sr-add-field-section { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 20px; margin-top: 20px; background: white; }
.sr-add-field-section h3 { margin: 0 0 14px; font-size: 15px; color: #1e293b; }
.sr-add-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
.sr-add-row input, .sr-add-row select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; }
.sr-add-btn { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none; border-radius: 8px; padding: 9px 18px; font-size: 13px; font-weight: 600; cursor: pointer; }
.sr-add-btn:hover { opacity: 0.9; }
.sr-enabled-toggle { display: flex; align-items: center; gap: 8px; font-size: 12px; margin-left: 4px; }
.sr-toggle { position: relative; width: 38px; height: 22px; }
.sr-toggle input { opacity: 0; width: 0; height: 0; }
.sr-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; border-radius: 22px; transition: 0.3s; }
.sr-toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.3s; }
.sr-toggle input:checked + .sr-toggle-slider { background: #6366f1; }
.sr-toggle input:checked + .sr-toggle-slider:before { transform: translateX(16px); }
</style>

<div class="sr-form-builder">
    <div id="sr-fields-container">
        <?php foreach ( $form_fields as $idx => $field ) :
            $is_core = ! empty( $field['core'] );
            $key = esc_attr( $field['key'] );
        ?>
        <div class="sr-field-row" data-index="<?php echo $idx; ?>">
            <span class="sr-drag-handle" title="Drag to reorder">⠿</span>

            <div class="sr-field-cell">
                <label>Type</label>
                <select name="sr_fields[<?php echo $idx; ?>][type]" class="sr-field-type">
                    <?php foreach ( array('text','email','tel','number','select','textarea','checkbox') as $t ) : ?>
                        <option value="<?php echo $t; ?>" <?php selected($field['type'], $t); ?>><?php echo ucfirst($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sr-field-cell">
                <label>Label</label>
                <input type="text" name="sr_fields[<?php echo $idx; ?>][label]" value="<?php echo esc_attr($field['label']); ?>" placeholder="Field Label">
            </div>

            <div class="sr-field-cell">
                <label>Placeholder</label>
                <input type="text" name="sr_fields[<?php echo $idx; ?>][placeholder]" value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>" placeholder="e.g. Enter your name">
            </div>

            <div class="sr-field-cell">
                <label>Key (internal)</label>
                <?php if ($is_core) : ?>
                    <input type="text" value="<?php echo $key; ?>" disabled style="background:#f3f4f6; color:#94a3b8; width:110px;">
                    <input type="hidden" name="sr_fields[<?php echo $idx; ?>][key]" value="<?php echo $key; ?>">
                <?php else : ?>
                    <input type="text" name="sr_fields[<?php echo $idx; ?>][key]" value="<?php echo $key; ?>" placeholder="e.g. city" style="width:110px;">
                <?php endif; ?>
            </div>

            <div class="sr-field-cell" style="min-width:70px;">
                <label>Required</label>
                <label class="sr-field-required-toggle">
                    <input type="checkbox" name="sr_fields[<?php echo $idx; ?>][required]" value="1" <?php checked(!empty($field['required'])); ?> <?php echo ($is_core && $field['key'] !== 'course') ? 'disabled checked' : ''; ?>>
                    <span>Yes</span>
                </label>
                <?php if ($is_core && $field['key'] !== 'course') : ?>
                    <input type="hidden" name="sr_fields[<?php echo $idx; ?>][required]" value="1">
                <?php endif; ?>
            </div>

            <div class="sr-field-cell" style="min-width:70px;">
                <label>Visible</label>
                <label class="sr-toggle">
                    <input type="checkbox" name="sr_fields[<?php echo $idx; ?>][enabled]" value="1" <?php checked(!isset($field['enabled']) || $field['enabled']); ?>>
                    <span class="sr-toggle-slider"></span>
                </label>
            </div>

            <input type="hidden" name="sr_fields[<?php echo $idx; ?>][core]" value="<?php echo $is_core ? '1' : '0'; ?>">

            <?php if ($is_core) : ?>
                <span class="sr-field-badge core">Core</span>
            <?php else : ?>
                <span class="sr-field-badge">Custom</span>
            <?php endif; ?>

            <button type="button" class="sr-remove-field" <?php echo $is_core ? 'disabled title="Core fields cannot be removed"' : ''; ?> data-index="<?php echo $idx; ?>">
                <?php echo $is_core ? '🔒 Core' : '✕ Remove'; ?>
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="sr-add-field-section">
        <h3>➕ Add Custom Field</h3>
        <div class="sr-add-row">
            <div>
                <label style="display:block;font-size:12px;color:#64748b;font-weight:600;margin-bottom:4px;">Type</label>
                <select id="sr-new-type">
                    <option value="text">Text</option>
                    <option value="email">Email</option>
                    <option value="tel">Phone</option>
                    <option value="number">Number</option>
                    <option value="select">Dropdown</option>
                    <option value="textarea">Textarea</option>
                    <option value="checkbox">Checkbox</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;color:#64748b;font-weight:600;margin-bottom:4px;">Label *</label>
                <input type="text" id="sr-new-label" placeholder="e.g. City" style="width:160px;">
            </div>
            <div>
                <label style="display:block;font-size:12px;color:#64748b;font-weight:600;margin-bottom:4px;">Placeholder</label>
                <input type="text" id="sr-new-placeholder" placeholder="e.g. Enter your city" style="width:160px;">
            </div>
            <div>
                <label style="display:block;font-size:12px;color:#64748b;font-weight:600;margin-bottom:4px;">Key (internal)</label>
                <input type="text" id="sr-new-key" placeholder="e.g. city" style="width:120px;">
            </div>
            <button type="button" class="sr-add-btn" onclick="srAddField()">Add Field</button>
        </div>
    </div>

    <input type="hidden" name="form_fields" id="sr-form-fields-json" value="">
</div>

<script>
(function() {
    var container  = document.getElementById('sr-fields-container');
    var jsonInput  = document.getElementById('sr-form-fields-json');
    var fieldCount = <?php echo count($form_fields); ?>;

    // Serialize current fields to JSON before form submit
    document.querySelector('form').addEventListener('submit', function() {
        var rows = container.querySelectorAll('.sr-field-row');
        var fields = [];
        rows.forEach(function(row) {
            var idx = row.dataset.index;
            var get = function(name) {
                var el = row.querySelector('[name="sr_fields[' + idx + '][' + name + ']"]');
                return el ? el.value : '';
            };
            var getCheck = function(name) {
                var el = row.querySelector('[name="sr_fields[' + idx + '][' + name + ']"]:not([disabled])');
                return el ? el.checked : false;
            };
            var enabledEl = row.querySelector('[name="sr_fields[' + idx + '][enabled]"]');
            var reqEl     = row.querySelector('[name="sr_fields[' + idx + '][required]"]:not([type="hidden"])');
            var coreEl    = row.querySelector('[name="sr_fields[' + idx + '][core]"]');
            fields.push({
                key:         get('key'),
                label:       get('label'),
                type:        get('type'),
                placeholder: get('placeholder'),
                required:    reqEl ? reqEl.checked : true,
                enabled:     enabledEl ? enabledEl.checked : true,
                core:        coreEl && coreEl.value === '1',
            });
        });
        jsonInput.value = JSON.stringify(fields);
        // Disable all the named sr_fields inputs so they don't double-submit
        container.querySelectorAll('[name^="sr_fields"]').forEach(function(el) { el.disabled = true; });
    });

    // Remove custom field row
    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('sr-remove-field') && !e.target.disabled) {
            e.target.closest('.sr-field-row').remove();
        }
    });

    // Drag-and-drop reorder
    var dragged = null;
    container.addEventListener('dragstart', function(e) {
        dragged = e.target.closest('.sr-field-row');
        if (dragged) { setTimeout(function() { dragged.style.opacity = '0.5'; }, 0); }
    });
    container.addEventListener('dragend', function(e) {
        if (dragged) { dragged.style.opacity = '1'; dragged = null; }
    });
    container.addEventListener('dragover', function(e) {
        e.preventDefault();
        var target = e.target.closest('.sr-field-row');
        if (target && dragged && target !== dragged) {
            var bounding = target.getBoundingClientRect();
            var offset   = bounding.y + bounding.height / 2;
            if (e.clientY - offset < 0) {
                container.insertBefore(dragged, target);
            } else {
                container.insertBefore(dragged, target.nextSibling);
            }
        }
    });
    // Make rows draggable
    Array.from(container.querySelectorAll('.sr-field-row')).forEach(function(r) { r.draggable = true; });

    // Add new field
    window.srAddField = function() {
        var label = document.getElementById('sr-new-label').value.trim();
        var key   = document.getElementById('sr-new-key').value.trim().replace(/[^a-z0-9_]/gi, '_').toLowerCase();
        var type  = document.getElementById('sr-new-type').value;
        var ph    = document.getElementById('sr-new-placeholder').value.trim();

        if (!label) { alert('Please enter a field label.'); return; }
        if (!key) { key = label.replace(/[^a-z0-9_]/gi, '_').toLowerCase(); }

        var idx = fieldCount++;
        var row = document.createElement('div');
        row.className = 'sr-field-row';
        row.dataset.index = idx;
        row.draggable = true;
        row.innerHTML = '<span class="sr-drag-handle" title="Drag to reorder">⠿</span>' +
            '<div class="sr-field-cell"><label>Type</label><select name="sr_fields[' + idx + '][type]">' +
            ['text','email','tel','number','select','textarea','checkbox'].map(function(t) {
                return '<option value="' + t + '"' + (t === type ? ' selected' : '') + '>' + t.charAt(0).toUpperCase() + t.slice(1) + '</option>';
            }).join('') +
            '</select></div>' +
            '<div class="sr-field-cell"><label>Label</label><input type="text" name="sr_fields[' + idx + '][label]" value="' + label + '" placeholder="Field Label"></div>' +
            '<div class="sr-field-cell"><label>Placeholder</label><input type="text" name="sr_fields[' + idx + '][placeholder]" value="' + ph + '" placeholder="e.g. Enter value"></div>' +
            '<div class="sr-field-cell"><label>Key (internal)</label><input type="text" name="sr_fields[' + idx + '][key]" value="' + key + '" style="width:110px;"></div>' +
            '<div class="sr-field-cell" style="min-width:70px;"><label>Required</label><label class="sr-field-required-toggle"><input type="checkbox" name="sr_fields[' + idx + '][required]" value="1"><span>Yes</span></label></div>' +
            '<div class="sr-field-cell" style="min-width:70px;"><label>Visible</label><label class="sr-toggle"><input type="checkbox" name="sr_fields[' + idx + '][enabled]" value="1" checked><span class="sr-toggle-slider"></span></label></div>' +
            '<input type="hidden" name="sr_fields[' + idx + '][core]" value="0">' +
            '<span class="sr-field-badge">Custom</span>' +
            '<button type="button" class="sr-remove-field" data-index="' + idx + '">✕ Remove</button>';
        container.appendChild(row);

        // Clear inputs
        document.getElementById('sr-new-label').value = '';
        document.getElementById('sr-new-key').value = '';
        document.getElementById('sr-new-placeholder').value = '';
    };
})();
</script>
