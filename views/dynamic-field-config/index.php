<?php
use MapasCulturais\i;
?>
<div class="dfc-page">

    <!-- HEADER -->
    <div class="dfc-header">
        <div class="dfc-header__title">
            <h1><?php i::_e('Configuración de Campos') ?></h1>
            <p class="dfc-header__subtitle"><?php i::_e('Personalice las etiquetas, obligatoriedad y visibilidad de los campos del formulario para cada tipo de entidad.') ?></p>
        </div>
        <div class="dfc-header__actions">
            <button type="button" class="dfc-btn dfc-btn--help" onclick="document.getElementById('dfc-help-modal').showModal()">
                <span class="dfc-icon">?</span> <?php i::_e('¿Cómo funciona?') ?>
            </button>
        </div>
    </div>

    <!-- MODAL DE AYUDA -->
    <dialog id="dfc-help-modal" class="dfc-modal">
        <div class="dfc-modal__content">
            <div class="dfc-modal__header">
                <h2><?php i::_e('Guía de Configuración de Campos') ?></h2>
                <button class="dfc-modal__close" onclick="document.getElementById('dfc-help-modal').close()" aria-label="Cerrar">✕</button>
            </div>
            <div class="dfc-modal__body">
                <div class="dfc-help-section">
                    <h3>📋 ¿Para qué sirve esta pantalla?</h3>
                    <p>Permite personalizar los campos de los formularios de <strong>Agentes, Espacios, Proyectos, Eventos y Oportunidades</strong> sin necesidad de modificar código. Los cambios se aplican inmediatamente para todos los usuarios.</p>
                </div>

                <div class="dfc-help-section">
                    <h3>🏷️ Nueva Etiqueta</h3>
                    <p>Cambia el <strong>nombre visible</strong> del campo. Si lo dejás vacío, se usa la etiqueta original del sistema.<br>
                    <em>Ejemplo: cambiar "Nome Completo ou Razão Social" → "Nombre completo"</em></p>
                </div>

                <div class="dfc-help-section">
                    <h3>✅ Obligatorio</h3>
                    <p>Marca el campo como <strong>requerido</strong>: el usuario no podrá guardar sin completarlo. Usá con cuidado para campos que realmente sean imprescindibles.</p>
                </div>

                <div class="dfc-help-section">
                    <h3>🚫 Ocultar</h3>
                    <p>Hace <strong>invisible</strong> el campo en el formulario. El campo no puede completarse pero tampoco se borra la información ya guardada.<br>
                    <em>Útil para campos de Brasil que no aplican en Uruguay.</em></p>
                </div>

                <div class="dfc-help-section dfc-help-section--warning">
                    <h3>⚠️ Importante</h3>
                    <ul>
                        <li>Los cambios afectan a <strong>todos los usuarios y registros</strong> del sistema.</li>
                        <li>No se puede ocultar Y marcar como obligatorio al mismo tiempo (sería contradictorio).</li>
                        <li>Guardá los cambios al final de cada pestaña (Agentes, Espacios, etc.).</li>
                        <li>Los cambios son inmediatos: no requieren reiniciar el sistema.</li>
                    </ul>
                </div>
            </div>
            <div class="dfc-modal__footer">
                <button class="dfc-btn dfc-btn--primary" onclick="document.getElementById('dfc-help-modal').close()"><?php i::_e('Entendido') ?></button>
            </div>
        </div>
    </dialog>

    <!-- FORMULARIO PRINCIPAL -->
    <form action="<?php echo $app->createUrl('dynamic-field-config', 'save'); ?>" method="POST" id="dfc-form">

        <!-- TABS DE ENTIDADES -->
        <div class="dfc-tabs">
            <div class="dfc-tabs__nav" role="tablist">
                <?php $first = true; foreach ($entities as $class => $data): ?>
                    <?php $tabId = 'dfc-tab-' . md5($class); ?>
                    <button type="button"
                            class="dfc-tabs__tab <?php echo $first ? 'dfc-tabs__tab--active' : ''; ?>"
                            data-tab="<?php echo $tabId; ?>"
                            role="tab"
                            aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                        <?php echo htmlspecialchars($data['label']); ?>
                        <?php
                        // Count modified fields
                        $modifiedCount = count(array_filter($data['overrides'], function($o) {
                            return !empty($o['label']) || isset($o['required']) || (isset($o['enabled']) && $o['enabled'] === false);
                        }));
                        if ($modifiedCount > 0): ?>
                            <span class="dfc-badge"><?php echo $modifiedCount; ?></span>
                        <?php endif; ?>
                    </button>
                <?php $first = false; endforeach; ?>
            </div>

            <div class="dfc-tabs__content">
                <?php $first = true; foreach ($entities as $class => $data): ?>
                    <?php $tabId = 'dfc-tab-' . md5($class); ?>
                    <div class="dfc-tabs__pane <?php echo $first ? 'dfc-tabs__pane--active' : ''; ?>"
                         id="<?php echo $tabId; ?>"
                         role="tabpanel">

                        <?php
                        // Group fields by category
                        $grouped = [];
                        foreach ($data['fields'] as $key => $field) {
                            $grouped[$field['group'] ?? 'Otros'][$key] = $field;
                        }
                        $groupOrder = ['Identificación', 'Datos Personales', 'Contacto', 'Dirección', 'Otros'];
                        foreach ($groupOrder as $groupName):
                            if (empty($grouped[$groupName])) continue;
                        ?>
                            <div class="dfc-group">
                                <div class="dfc-group__header">
                                    <span class="dfc-group__icon"><?php
                                        $icons = ['Identificación'=>'👤','Datos Personales'=>'📝','Contacto'=>'📞','Dirección'=>'📍','Otros'=>'⚙️'];
                                        echo $icons[$groupName] ?? '⚙️';
                                    ?></span>
                                    <h3 class="dfc-group__title"><?php echo htmlspecialchars($groupName); ?></h3>
                                    <span class="dfc-group__count"><?php echo count($grouped[$groupName]); ?> campos</span>
                                </div>

                                <table class="dfc-table">
                                    <thead>
                                        <tr>
                                            <th class="dfc-table__th--label">Etiqueta actual</th>
                                            <th class="dfc-table__th--new">Nueva etiqueta <small>(vacío = usar original)</small></th>
                                            <th class="dfc-table__th--opts">Obligatorio</th>
                                            <th class="dfc-table__th--opts">Ocultar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grouped[$groupName] as $key => $field):
                                            $override = $data['overrides'][$key] ?? [];
                                            $newLabel  = $override['label'] ?? '';
                                            $isRequired = isset($override['required']) ? (bool)$override['required'] : $field['required'];
                                            $isHidden   = isset($override['enabled'])  ? !$override['enabled']  : false;
                                            $isModified = !empty($newLabel) || ($isRequired !== $field['required']) || $isHidden;
                                        ?>
                                            <tr class="dfc-row <?php echo $isModified ? 'dfc-row--modified' : ''; ?> <?php echo $isHidden ? 'dfc-row--hidden' : ''; ?>">
                                                <td class="dfc-cell--label">
                                                    <span class="dfc-field-label"><?php echo htmlspecialchars($field['original_label']); ?></span>
                                                    <code class="dfc-field-key"><?php echo htmlspecialchars($key); ?></code>
                                                </td>
                                                <td class="dfc-cell--new">
                                                    <input type="text"
                                                           name="config[<?php echo $class; ?>][<?php echo $key; ?>][label]"
                                                           value="<?php echo htmlspecialchars($newLabel); ?>"
                                                           class="dfc-input"
                                                           placeholder="<?php echo htmlspecialchars($field['original_label']); ?>">
                                                </td>
                                                <td class="dfc-cell--check">
                                                    <input type="hidden" name="config[<?php echo $class; ?>][<?php echo $key; ?>][required]" value="0">
                                                    <label class="dfc-toggle">
                                                        <input type="checkbox"
                                                               name="config[<?php echo $class; ?>][<?php echo $key; ?>][required]"
                                                               value="1"
                                                               <?php echo $isRequired ? 'checked' : ''; ?>>
                                                        <span class="dfc-toggle__slider"></span>
                                                    </label>
                                                </td>
                                                <td class="dfc-cell--check">
                                                    <input type="hidden" name="config[<?php echo $class; ?>][<?php echo $key; ?>][enabled]" value="1">
                                                    <label class="dfc-toggle dfc-toggle--danger">
                                                        <input type="checkbox"
                                                               name="config[<?php echo $class; ?>][<?php echo $key; ?>][enabled]"
                                                               value="0"
                                                               <?php echo $isHidden ? 'checked' : ''; ?>>
                                                        <span class="dfc-toggle__slider"></span>
                                                    </label>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>

                    </div>
                <?php $first = false; endforeach; ?>
            </div>
        </div>

        <!-- BOTÓN GUARDAR -->
        <div class="dfc-footer">
            <div class="dfc-footer__info">
                <span>💡 Los cambios se aplican a todos los usuarios inmediatamente.</span>
            </div>
            <button type="submit" class="dfc-btn dfc-btn--primary dfc-btn--large">
                💾 <?php i::_e('Guardar Configuración') ?>
            </button>
        </div>
    </form>
</div>

<style>
/* ── DFC Layout ── */
.dfc-page { padding: 24px 32px; max-width: 1100px; }

/* ── Header ── */
.dfc-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 28px; }
.dfc-header h1 { margin: 0 0 6px; font-size: 1.6rem; font-weight: 700; color: #1a1a2e; }
.dfc-header__subtitle { margin: 0; color: #6b7280; font-size: 0.93rem; }
.dfc-header__actions { flex-shrink: 0; }

/* ── Buttons ── */
.dfc-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; border: none; transition: all .2s; }
.dfc-btn--help { background: #f0f4ff; color: #4361ee; border: 1.5px solid #c7d2fe; }
.dfc-btn--help:hover { background: #e0e7ff; }
.dfc-btn--primary { background: #4361ee; color: #fff; }
.dfc-btn--primary:hover { background: #3451d1; }
.dfc-btn--large { padding: 12px 28px; font-size: 1rem; }
.dfc-icon { display: inline-flex; width: 18px; height: 18px; border-radius: 50%; background: #4361ee; color: #fff; font-size: 0.75rem; font-weight: 700; align-items: center; justify-content: center; }
.dfc-btn--help .dfc-icon { background: #4361ee; }

/* ── Modal ── */
.dfc-modal { border: none; border-radius: 16px; padding: 0; max-width: 600px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
.dfc-modal::backdrop { background: rgba(0,0,0,.5); backdrop-filter: blur(4px); }
.dfc-modal__header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #e5e7eb; }
.dfc-modal__header h2 { margin: 0; font-size: 1.2rem; color: #1a1a2e; }
.dfc-modal__close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #6b7280; padding: 4px 8px; border-radius: 6px; }
.dfc-modal__close:hover { background: #f3f4f6; color: #111; }
.dfc-modal__body { padding: 0 24px; max-height: 65vh; overflow-y: auto; }
.dfc-modal__footer { padding: 16px 24px; display: flex; justify-content: flex-end; border-top: 1px solid #e5e7eb; }
.dfc-help-section { padding: 16px 0; border-bottom: 1px solid #f3f4f6; }
.dfc-help-section:last-child { border-bottom: none; }
.dfc-help-section h3 { margin: 0 0 8px; font-size: 1rem; color: #1a1a2e; }
.dfc-help-section p, .dfc-help-section ul { margin: 0; color: #4b5563; font-size: 0.9rem; line-height: 1.6; }
.dfc-help-section ul { padding-left: 20px; }
.dfc-help-section ul li { margin-bottom: 4px; }
.dfc-help-section--warning { background: #fffbeb; border-radius: 10px; padding: 14px 16px; margin: 8px 0; }
.dfc-help-section--warning h3 { color: #92400e; }
.dfc-help-section--warning p, .dfc-help-section--warning ul { color: #78350f; }

/* ── Tabs ── */
.dfc-tabs__nav { display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; margin-bottom: 0; flex-wrap: wrap; }
.dfc-tabs__tab { background: none; border: none; padding: 10px 18px; font-size: 0.9rem; font-weight: 500; color: #6b7280; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; border-radius: 6px 6px 0 0; transition: all .15s; display: flex; align-items: center; gap: 6px; }
.dfc-tabs__tab:hover { color: #4361ee; background: #f0f4ff; }
.dfc-tabs__tab--active { color: #4361ee; border-bottom-color: #4361ee; font-weight: 600; background: #f0f4ff; }
.dfc-badge { background: #4361ee; color: #fff; border-radius: 999px; padding: 1px 7px; font-size: 0.72rem; font-weight: 700; }
.dfc-tabs__content { padding-top: 24px; }
.dfc-tabs__pane { display: none !important; }
.dfc-tabs__pane--active { display: block !important; }

/* ── Groups ── */
.dfc-group { margin-bottom: 28px; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.dfc-group__header { display: flex; align-items: center; gap: 10px; padding: 12px 18px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
.dfc-group__icon { font-size: 1.2rem; }
.dfc-group__title { margin: 0; font-size: 0.95rem; font-weight: 600; color: #374151; }
.dfc-group__count { margin-left: auto; font-size: 0.78rem; color: #9ca3af; background: #e5e7eb; padding: 2px 8px; border-radius: 999px; }

/* ── Table ── */
.dfc-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.dfc-table thead { background: #f3f4f6; }
.dfc-table th { padding: 8px 14px; text-align: left; font-size: 0.78rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
.dfc-table__th--opts { width: 90px; text-align: center; }
.dfc-table__th--new { width: 30%; }
.dfc-row { border-bottom: 1px solid #f3f4f6; transition: background .15s; }
.dfc-row:last-child { border-bottom: none; }
.dfc-row:hover { background: #f9fafb; }
.dfc-row--modified { background: #fefce8 !important; }
.dfc-row--hidden { opacity: .6; background: #f9fafb !important; }
.dfc-cell--label, .dfc-cell--new, .dfc-cell--check { padding: 10px 14px; vertical-align: middle; }
.dfc-cell--check { text-align: center; }
.dfc-table .dfc-cell--label .dfc-field-label { display: block !important; font-weight: 500; color: #1f2937 !important; line-height: 1.3; }
.dfc-table .dfc-cell--label .dfc-field-key { display: block !important; font-size: 0.75rem !important; color: #9ca3af !important; font-family: monospace !important; margin-top: 3px; background: #f3f4f6 !important; padding: 1px 5px !important; border-radius: 3px; width: fit-content; border: none !important; box-shadow: none !important; }
.dfc-input { width: 100%; padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.88rem; color: #1f2937; background: #fff; transition: border-color .15s; }
.dfc-input:focus { outline: none; border-color: #4361ee; box-shadow: 0 0 0 3px rgba(67,97,238,.15); }
.dfc-input::placeholder { color: #9ca3af; font-style: italic; }

/* ── Toggle Switches ── */
.dfc-toggle { position: relative; display: inline-flex; width: 40px; height: 22px; cursor: pointer; }
.dfc-toggle input { opacity: 0; width: 0; height: 0; }
.dfc-toggle__slider { position: absolute; inset: 0; background: #d1d5db; border-radius: 22px; transition: .25s; }
.dfc-toggle__slider::before { content: ''; position: absolute; height: 16px; width: 16px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .25s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
.dfc-toggle input:checked ~ .dfc-toggle__slider { background: #4361ee; }
.dfc-toggle--danger input:checked ~ .dfc-toggle__slider { background: #ef4444; }
.dfc-toggle input:checked ~ .dfc-toggle__slider::before { transform: translateX(18px); }

/* ── Footer ── */
.dfc-footer { display: flex; align-items: center; justify-content: space-between; padding: 20px 0 8px; gap: 16px; border-top: 1px solid #e5e7eb; margin-top: 12px; }
.dfc-footer__info { font-size: 0.85rem; color: #6b7280; }
</style>

<script>
// Tab switching — also applies inline style to ensure panel CSS doesn't override display:none
function dfcShowTab(targetId) {
    document.querySelectorAll('.dfc-tabs__pane').forEach(function(p) {
        p.classList.remove('dfc-tabs__pane--active');
        p.style.setProperty('display', 'none', 'important');
    });
    document.querySelectorAll('.dfc-tabs__tab').forEach(function(b) {
        b.classList.remove('dfc-tabs__tab--active');
        b.setAttribute('aria-selected', 'false');
    });
    var target = document.getElementById(targetId);
    if (target) {
        target.classList.add('dfc-tabs__pane--active');
        target.style.setProperty('display', 'block', 'important');
    }
}

// Initialize: hide all panes except first
(function() {
    var panes = document.querySelectorAll('.dfc-tabs__pane');
    panes.forEach(function(p, i) {
        if (i === 0) {
            p.style.setProperty('display', 'block', 'important');
        } else {
            p.style.setProperty('display', 'none', 'important');
        }
    });
})();

document.querySelectorAll('.dfc-tabs__tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
        dfcShowTab(this.dataset.tab);
        this.classList.add('dfc-tabs__tab--active');
        this.setAttribute('aria-selected', 'true');
    });
});

// Visual feedback: resaltar fila cuando se modifica
document.querySelectorAll('.dfc-input').forEach(function(input) {
    input.addEventListener('input', function() {
        this.closest('.dfc-row').classList.toggle('dfc-row--modified', this.value.trim() !== '');
    });
});

// Advertencia: no tiene sentido ocultar Y obligatorio
document.querySelectorAll('[name$="[enabled]"][value="0"]').forEach(function(hideCheck) {
    hideCheck.addEventListener('change', function() {
        var row = this.closest('.dfc-row');
        var reqCheck = row.querySelector('[name$="[required]"][value="1"]');
        if (this.checked && reqCheck && reqCheck.checked) {
            reqCheck.checked = false;
            alert('Un campo oculto no puede ser obligatorio. Se desmarcó "Obligatorio".');
        }
        row.classList.toggle('dfc-row--hidden', this.checked);
    });
});
// Forzar separación visual label/key por si el CSS del panel interfiere
document.querySelectorAll('.dfc-field-label').forEach(function(el) {
    el.style.cssText = 'display:block !important; font-weight:600; color:#1f2937; line-height:1.4;';
});
document.querySelectorAll('.dfc-field-key').forEach(function(el) {
    el.style.cssText = 'display:block !important; font-size:0.73rem; color:#9ca3af; font-family:monospace; margin-top:3px; background:#f1f5f9; padding:1px 6px; border-radius:3px; width:fit-content; border:none; box-shadow:none;';
});
</script>
