<?php
/*
-------------------------------------------------------------------------
MailAnalyzer plugin for GLPI
Copyright (C) 2011-2025 by Raynet SAS a company of A.Raymond Network.

https://www.araymond.com/
-------------------------------------------------------------------------

LICENSE

This file is part of MailAnalyzer plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */

/**
 * Configuration class for the MailAnalyzer plugin.
 * Provides a single settings tab in the GLPI Configuration page
 * with both configuration and statistics.
 */
class PluginMailanalyzerConfig extends CommonDBTM
{

   /**
    * @param int $nb Plural count
    * @return string
    */
   public static function getTypeName($nb = 0): string
   {
      return __('Mail Analyzer setup', 'mailanalyzer');
   }

   /**
    * @param bool $with_comment Include comment
    * @return string
    */
   public function getName($with_comment = 0): string
   {
      return __('MailAnalyzer', 'mailanalyzer');
   }

   /**
    * Icon displayed in the sidebar tab.
    * @return string FontAwesome icon class
    */
   public static function getIcon(): string
   {
      return 'ti ti-mail';
   }


   /**
    * Display the configuration form for the plugin.
    *
    * @param CommonGLPI $item The config item
    * @return bool
    */
   public static function showConfigForm(CommonGLPI $item): bool
   {
      $config = Config::getConfigurationValues('plugin:mailanalyzer');

      if (!isset($config['use_threadindex'])) {
         $config['use_threadindex'] = 0;
      }

      $period = $_SESSION['plugin_mailanalyzer_stats_period'] ?? '30days';

      echo "<div class='mailanalyzer'>";

      // ---- Hero ----
      self::renderHero();

      // ---- Settings panel ----
      echo "<form name='form' action=\"" . Toolbox::getItemTypeFormURL('Config') . "\" method='post' data-track-changes='true'>";
      echo "<div class='ma-panel'>";
      echo "<div class='ma-panel__head'>";
      echo "<span class='ma-panel__icon'><i class='ti ti-adjustments'></i></span>";
      echo "<div class='ma-panel__title'>" . __('Mail Analyzer setup', 'mailanalyzer');
      echo "<small>" . __('Tune how incoming emails are grouped and filtered', 'mailanalyzer') . "</small>";
      echo "</div></div>";
      echo "<div class='ma-panel__body'>";

      // Thread-Index option
      echo "<div class='ma-field'>";
      echo "<div class='ma-field__label'><i class='ti ti-affiliate'></i> " . __('Use of Thread index', 'mailanalyzer');
      echo "<span class='ma-field__hint'>" . __('Enable Microsoft Exchange Thread-Index header support for improved conversation tracking', 'mailanalyzer') . "</span>";
      echo "</div>";
      echo "<div class='ma-field__control'>";
      Dropdown::showYesNo("use_threadindex", $config['use_threadindex']);
      echo "</div></div>";

      // Whitelist
      echo "<div class='ma-field'>";
      echo "<div class='ma-field__label'><i class='ti ti-shield-check'></i> " . __('Whitelist Domains', 'mailanalyzer');
      echo "<span class='ma-field__hint'>" . __('Never block emails from these domains (one per line, e.g., @important.com)', 'mailanalyzer') . "</span>";
      echo "</div>";
      echo "<div class='ma-field__control'>";
      echo "<textarea name='whitelist_domains' class='form-control' rows='3' placeholder='@trust.com'>" . Html::entities_deep($config['whitelist_domains'] ?? '') . "</textarea>";
      echo "</div></div>";

      // Blacklist
      echo "<div class='ma-field'>";
      echo "<div class='ma-field__label'><i class='ti ti-ban'></i> " . __('Blacklist Domains', 'mailanalyzer');
      echo "<span class='ma-field__hint'>" . __('Always block emails from these domains (one per line, e.g., @spam.com)', 'mailanalyzer') . "</span>";
      echo "</div>";
      echo "<div class='ma-field__control'>";
      echo "<textarea name='blacklist_domains' class='form-control' rows='3' placeholder='@spam.com'>" . Html::entities_deep($config['blacklist_domains'] ?? '') . "</textarea>";
      echo "</div></div>";

      // Duplicate-flood alert: threshold
      echo "<div class='ma-field'>";
      echo "<div class='ma-field__label'><i class='ti ti-bell-ringing'></i> " . __('Duplicate flood alert', 'mailanalyzer');
      echo "<span class='ma-field__hint'>" . __('Raise an alert every N duplicate emails blocked within the time window below. Set to 0 to disable.', 'mailanalyzer') . "</span>";
      echo "</div>";
      echo "<div class='ma-field__control'>";
      echo "<input type='number' min='0' step='1' name='duplicate_alert_threshold' class='form-control' value='" . (int) ($config['duplicate_alert_threshold'] ?? 20) . "'>";
      echo "</div></div>";

      // Duplicate-flood alert: window (minutes)
      echo "<div class='ma-field'>";
      echo "<div class='ma-field__label'><i class='ti ti-clock'></i> " . __('Alert window (minutes)', 'mailanalyzer');
      echo "<span class='ma-field__hint'>" . __('Time window used to count blocked duplicates for the flood alert.', 'mailanalyzer') . "</span>";
      echo "</div>";
      echo "<div class='ma-field__control'>";
      echo "<input type='number' min='1' step='1' name='duplicate_alert_window' class='form-control' value='" . max(1, (int) ($config['duplicate_alert_window'] ?? 60)) . "'>";
      echo "</div></div>";

      // How it works note
      echo "<div class='ma-note'>";
      echo "<i class='ti ti-info-circle ma-note__icon'></i>";
      echo "<div class='ma-note__body'><strong>" . __('How it works', 'mailanalyzer') . "</strong>";
      echo __('This plugin analyzes email headers (Message-ID, References, Thread-Index) to automatically combine related emails into the same ticket, preventing duplicates when CC recipients use "Reply to All".', 'mailanalyzer');
      echo "</div></div>";

      // Actions
      echo "<div class='ma-actions'>";
      echo "<button type='submit' name='update' class='ma-btn'><i class='ti ti-device-floppy'></i> " . _sx('button', 'Save') . "</button>";
      echo "</div>";

      echo "</div></div>"; // .ma-panel__body / .ma-panel

      echo "<input type='hidden' name='id' value='1'>";
      echo "<input type='hidden' name='config_context' value='plugin:mailanalyzer'>";
      Html::closeForm();

      // ---- Statistics dashboard ----
      PluginMailanalyzerStats::showDashboard($period);

      // ---- Health check ----
      self::showHealthCheck();

      // ---- Recent activity ----
      PluginMailanalyzerStats::showRecentActivity();

      echo "</div>"; // .mailanalyzer

      return false;
   }

   /**
    * Render the branded hero header of the Mail Analyzer tab.
    *
    * @return void
    */
   private static function renderHero(): void
   {
      echo "<div class='ma-hero'>";
      echo "<div class='ma-hero__brand'>";
      echo "<span class='ma-logo'><i class='ti ti-mail-opened'></i></span>";
      echo "<div>";
      echo "<div class='ma-hero__eyebrow'>GLPI &middot; " . __('Mail Analyzer', 'mailanalyzer') . "</div>";
      echo "<h2 class='ma-hero__title'>" . __('Intelligent email conversation tracking', 'mailanalyzer') . "</h2>";
      echo "<p class='ma-hero__subtitle'>" . __('Combines related emails into a single ticket and blocks duplicates automatically.', 'mailanalyzer') . "</p>";
      echo "</div></div>";
      echo "</div>";
   }

   /**
    * Display Health Check for Mail Collectors.
    *
    * @return void
    */
   public static function showHealthCheck(): void
   {
      global $DB;

      echo "<div class='ma-panel'>";
      echo "<div class='ma-panel__head'>";
      echo "<span class='ma-panel__icon'><i class='ti ti-activity'></i></span>";
      echo "<div class='ma-panel__title'>" . __('Mail Collectors Health Check', 'mailanalyzer');
      echo "<small>" . __('Connection status and last processed email per collector', 'mailanalyzer') . "</small>";
      echo "</div></div>";
      echo "<div class='ma-panel__body'>";

      $res = $DB->request(['FROM' => 'glpi_mailcollectors']);

      if (!count($res)) {
         echo "<div class='ma-empty'><i class='ti ti-inbox-off'></i>" . __('No active mail collectors found.', 'mailanalyzer') . "</div>";
         echo "</div></div>";
         return;
      }

      echo "<table class='ma-table'>";
      echo "<thead><tr>";
      echo "<th>" . __('Collector Name', 'mailanalyzer') . "</th>";
      echo "<th>" . __('Connection Status', 'mailanalyzer') . "</th>";
      echo "<th>" . __('Last Email Processed (Analyzer)', 'mailanalyzer') . "</th>";
      echo "<th>" . __('Duplicates blocked (30d)', 'mailanalyzer') . "</th>";
      echo "<th>" . __('Followups created (30d)', 'mailanalyzer') . "</th>";
      echo "<th>" . __('Errors Count', 'mailanalyzer') . "</th>";
      echo "</tr></thead><tbody>";

      foreach ($res as $mc) {
         echo "<tr>";

         // Name
         echo "<td><i class='ti ti-inbox ma-cell-icon'></i>" . htmlspecialchars($mc['name']) . "</td>";

         // Connection Status
         $hasErrors = (int) $mc['errors'] > 0;
         if ($hasErrors) {
            echo "<td><span class='ma-badge ma-badge--danger'><i class='ti ti-alert-triangle'></i> " . __('Failing', 'mailanalyzer') . "</span></td>";
         } else {
            echo "<td><span class='ma-badge ma-badge--success'><i class='ti ti-circle-check'></i> " . __('OK', 'mailanalyzer') . "</span></td>";
         }

         // Last Email Processed
         $lastDate = "<span class='ma-dash'>" . __('Never', 'mailanalyzer') . "</span>";
         $resStats = $DB->request([
            'SELECT' => ['MAX' => 'date_created AS last_date'],
            'FROM'   => 'glpi_plugin_mailanalyzer_stats',
            'WHERE'  => ['mailcollectors_id' => $mc['id']]
         ]);
         if ($row = $resStats->current()) {
            if (!empty($row['last_date'])) {
               $lastDate = Html::convDateTime($row['last_date']);
            }
         }
         echo "<td>" . $lastDate . "</td>";

         // 30-day activity counts (duplicates blocked / followups created)
         $activity = [];
         $resAct = $DB->request([
            'SELECT'  => ['action_type', 'COUNT' => 'action_type AS count'],
            'FROM'    => 'glpi_plugin_mailanalyzer_stats',
            'WHERE'   => [
               'mailcollectors_id' => $mc['id'],
               'date_created'      => ['>=', date('Y-m-d H:i:s', strtotime('-30 days'))]
            ],
            'GROUPBY' => 'action_type'
         ]);
         foreach ($resAct as $a) {
            $activity[$a['action_type']] = (int) $a['count'];
         }
         $dupCount = $activity[PluginMailanalyzerStats::ACTION_DUPLICATE_BLOCKED] ?? 0;
         $fupCount = $activity[PluginMailanalyzerStats::ACTION_FOLLOWUP_CREATED] ?? 0;
         echo "<td>" . ($dupCount > 0
            ? "<span class='ma-badge ma-badge--primary'>$dupCount</span>"
            : "<span class='ma-badge ma-badge--muted'>0</span>") . "</td>";
         echo "<td>" . ($fupCount > 0
            ? "<span class='ma-badge ma-badge--success'>$fupCount</span>"
            : "<span class='ma-badge ma-badge--muted'>0</span>") . "</td>";

         // Errors Count
         $errCount = (int) $mc['errors'];
         echo "<td>" . ($errCount > 0
            ? "<span class='ma-badge ma-badge--danger'>$errCount</span>"
            : "<span class='ma-badge ma-badge--muted'>0</span>") . "</td>";

         echo "</tr>";
      }

      echo "</tbody></table>";
      echo "</div></div>";
   }


   /**
    * @param CommonGLPI $item
    * @param int $withtemplate
    * @return string
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
   {
      if ($item->getType() == 'Config') {
         return "<span class='d-flex align-items-center'><i class='ti ti-mail me-2'></i>" . __('Mail Analyzer', 'mailanalyzer') . "</span>";
      }
      return '';
   }


   /**
    * @param CommonGLPI $item
    * @param int $tabnum
    * @param int $withtemplate
    * @return bool
    */
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
   {
      if ($item->getType() == 'Config') {
         self::showConfigForm($item);
      }
      return true;
   }
}
