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
 * Statistics tracking and dashboard display for the MailAnalyzer plugin.
 * Records email processing events and provides a visual summary.
 */
class PluginMailanalyzerStats extends CommonDBTM
{
   // Action type constants
   public const ACTION_DUPLICATE_BLOCKED = 'duplicate_blocked';
   public const ACTION_FOLLOWUP_CREATED  = 'followup_created';
   public const ACTION_TICKET_LINKED     = 'ticket_linked';
   public const ACTION_NEW_TICKET        = 'new_ticket';

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_mailanalyzer_stats';
   }

   /**
    * Record a stats event in the database.
    *
    * @param string $action_type One of the ACTION_* constants
    * @param int    $tickets_id Related ticket ID
    * @param int    $mailcollectors_id Mail collector ID
    * @param string $message_id Email Message-ID
    * @return void
    */
   public static function record(
      string $action_type,
      int $tickets_id = 0,
      int $mailcollectors_id = 0,
      string $message_id = ''
   ): void {
      global $DB;

      $result = $DB->insert(
         'glpi_plugin_mailanalyzer_stats',
         [
            'action_type'       => $action_type,
            'tickets_id'        => $tickets_id,
            'mailcollectors_id' => $mailcollectors_id,
            'message_id'        => $message_id,
         ]
      );
      if (!$result) {
         Toolbox::logError("MailAnalyzer: Failed to record stat event: $action_type");
      }
   }

   /**
    * Get summary counts for the dashboard.
    *
    * @param string $period 'all', '7days', '30days', '90days'
    * @return array<string, int>
    */
   public static function getSummary(string $period = 'all'): array
   {
      global $DB;

      $where = [];
      switch ($period) {
         case '7days':
            $where['date_created'] = ['>=', date('Y-m-d H:i:s', strtotime('-7 days'))];
            break;
         case '30days':
            $where['date_created'] = ['>=', date('Y-m-d H:i:s', strtotime('-30 days'))];
            break;
         case '90days':
            $where['date_created'] = ['>=', date('Y-m-d H:i:s', strtotime('-90 days'))];
            break;
      }

      $summary = [
         self::ACTION_DUPLICATE_BLOCKED => 0,
         self::ACTION_FOLLOWUP_CREATED  => 0,
         self::ACTION_TICKET_LINKED     => 0,
         self::ACTION_NEW_TICKET        => 0,
      ];

      $criteria = [
         'SELECT' => ['action_type', 'COUNT' => 'action_type AS count'],
         'FROM'   => 'glpi_plugin_mailanalyzer_stats',
         'GROUPBY' => 'action_type',
      ];

      if (!empty($where)) {
         $criteria['WHERE'] = $where;
      }

      $res = $DB->request($criteria);
      foreach ($res as $row) {
         if (isset($summary[$row['action_type']])) {
            $summary[$row['action_type']] = (int) $row['count'];
         }
      }

      return $summary;
   }

   /**
    * Get recent events for the activity log.
    *
    * @param int $limit Number of events to return
    * @return array
    */
   public static function getRecentEvents(int $limit = 10): array
   {
      global $DB;

      $events = [];
      $res = $DB->request([
         'FROM'    => 'glpi_plugin_mailanalyzer_stats',
         'ORDER'   => 'date_created DESC',
         'LIMIT'   => $limit,
      ]);

      foreach ($res as $row) {
         $events[] = $row;
      }
      return $events;
   }

   /**
    * Display the statistics dashboard (KPI tiles + period filter) in the config tab.
    *
    * @param string $period Period filter
    * @return void
    */
   public static function showDashboard(string $period = '30days'): void
   {
      $summary = self::getSummary($period);
      $total = array_sum($summary);

      $periods = [
         '7days'  => __('Last 7 days', 'mailanalyzer'),
         '30days' => __('Last 30 days', 'mailanalyzer'),
         '90days' => __('Last 90 days', 'mailanalyzer'),
         'all'    => __('All time', 'mailanalyzer'),
      ];

      $cards = [
         self::ACTION_DUPLICATE_BLOCKED => ['danger',  'ti-copy-off',    __('Duplicates Blocked', 'mailanalyzer')],
         self::ACTION_FOLLOWUP_CREATED  => ['success', 'ti-message-2',   __('Followups Created', 'mailanalyzer')],
         self::ACTION_TICKET_LINKED     => ['violet',  'ti-link',        __('Tickets Linked', 'mailanalyzer')],
         self::ACTION_NEW_TICKET        => ['primary', 'ti-ticket',      __('New Tickets', 'mailanalyzer')],
      ];

      echo "<div class='ma-panel'>";

      // Head with segmented period filter
      echo "<div class='ma-panel__head'>";
      echo "<span class='ma-panel__icon'><i class='ti ti-chart-bar'></i></span>";
      echo "<div class='ma-panel__title'>" . __('Mail Analyzer Statistics', 'mailanalyzer');
      echo "<small>" . __('Email processing activity over the selected period', 'mailanalyzer') . "</small>";
      echo "</div>";

      echo "<div class='ma-hero__aside' style='margin-left:auto'>";
      echo "<form method='post' action='" . Plugin::getWebDir('mailanalyzer') . "/front/stats.php'>";
      echo "<input type='hidden' name='filter_stats' value='1'>";
      echo "<div class='ma-segment'>";
      foreach ($periods as $key => $label) {
         $active = $key === $period ? ' is-active' : '';
         echo "<button type='submit' name='period' value='" . $key . "' class='ma-segment__btn$active'>" . $label . "</button>";
      }
      echo "</div>";
      Html::closeForm();
      echo "</div>";
      echo "</div>"; // .ma-panel__head

      echo "<div class='ma-panel__body'>";

      // KPI tiles
      echo "<div class='ma-kpis'>";
      foreach ($cards as $key => [$variant, $icon, $label]) {
         $count = $summary[$key];
         $share = $total > 0 ? round(($count / $total) * 100) : 0;
         echo "<div class='ma-kpi ma-kpi--$variant'>";
         echo "<div class='ma-kpi__top'>";
         echo "<span class='ma-kpi__icon'><i class='ti $icon'></i></span>";
         echo "<span class='ma-kpi__value'>" . $count . "</span>";
         echo "</div>";
         echo "<span class='ma-kpi__label'>" . $label . "</span>";
         echo "<div class='ma-kpi__bar'><span style='width:" . $share . "%'></span></div>";
         echo "<span class='ma-kpi__share'>" . $share . "% " . __('of total', 'mailanalyzer') . "</span>";
         echo "</div>";
      }
      echo "</div>"; // .ma-kpis

      // Total strip
      echo "<div class='ma-total'><i class='ti ti-mail'></i> " . __('Total emails processed', 'mailanalyzer') . " <b>" . $total . "</b></div>";

      echo "</div></div>"; // .ma-panel__body / .ma-panel
   }


   /**
    * Display the recent activity log as a branded table.
    *
    * @param int $limit Number of events to show
    * @return void
    */
   public static function showRecentActivity(int $limit = 15): void
   {
      $events = self::getRecentEvents($limit);

      echo "<div class='ma-panel'>";
      echo "<div class='ma-panel__head'>";
      echo "<span class='ma-panel__icon'><i class='ti ti-history'></i></span>";
      echo "<div class='ma-panel__title'>" . __('Recent Activity', 'mailanalyzer');
      echo "<small>" . __('Latest emails analyzed by the plugin', 'mailanalyzer') . "</small>";
      echo "</div></div>";
      echo "<div class='ma-panel__body'>";

      if (empty($events)) {
         echo "<div class='ma-empty'><i class='ti ti-clock-off'></i>" . __('No activity recorded yet.', 'mailanalyzer') . "</div>";
         echo "</div></div>";
         return;
      }

      $actionLabels = [
         self::ACTION_DUPLICATE_BLOCKED => "<span class='ma-badge ma-badge--danger'><i class='ti ti-copy-off'></i> " . __('Duplicate Blocked', 'mailanalyzer') . "</span>",
         self::ACTION_FOLLOWUP_CREATED  => "<span class='ma-badge ma-badge--success'><i class='ti ti-message-2'></i> " . __('Followup Created', 'mailanalyzer') . "</span>",
         self::ACTION_TICKET_LINKED     => "<span class='ma-badge ma-badge--violet'><i class='ti ti-link'></i> " . __('Ticket Linked', 'mailanalyzer') . "</span>",
         self::ACTION_NEW_TICKET        => "<span class='ma-badge ma-badge--primary'><i class='ti ti-ticket'></i> " . __('New Ticket', 'mailanalyzer') . "</span>",
      ];

      echo "<table class='ma-table'>";
      echo "<thead><tr>";
      echo "<th>" . __('Date', 'mailanalyzer') . "</th>";
      echo "<th>" . __('Action', 'mailanalyzer') . "</th>";
      echo "<th>" . __('Ticket', 'mailanalyzer') . "</th>";
      echo "<th>" . __('Mail Collector', 'mailanalyzer') . "</th>";
      echo "<th>" . __('Message ID', 'mailanalyzer') . "</th>";
      echo "</tr></thead><tbody>";

      foreach ($events as $event) {
         echo "<tr>";
         echo "<td>" . Html::convDateTime($event['date_created']) . "</td>";
         echo "<td>" . ($actionLabels[$event['action_type']] ?? htmlspecialchars($event['action_type'])) . "</td>";

         // Ticket link
         if ($event['tickets_id'] > 0) {
            $ticket = new Ticket();
            if ($ticket->getFromDB($event['tickets_id'])) {
               echo "<td><a href='" . Ticket::getFormURLWithID($event['tickets_id']) . "'>";
               echo "<i class='ti ti-hash'></i>" . $event['tickets_id'] . " — " . htmlspecialchars($ticket->getName());
               echo "</a></td>";
            } else {
               echo "<td><i class='ti ti-hash ma-cell-icon'></i>" . $event['tickets_id'] . " <span class='ma-dash'>(" . __('deleted', 'mailanalyzer') . ")</span></td>";
            }
         } else {
            echo "<td><span class='ma-dash'>—</span></td>";
         }

         // Mail collector
         if ($event['mailcollectors_id'] > 0) {
            echo "<td><i class='ti ti-inbox ma-cell-icon'></i>#" . $event['mailcollectors_id'] . "</td>";
         } else {
            echo "<td><span class='ma-dash'>—</span></td>";
         }

         // Message ID (truncated)
         $msgId = htmlspecialchars($event['message_id']);
         $shortId = strlen($msgId) > 40 ? substr($msgId, 0, 40) . '…' : $msgId;
         echo "<td><span class='ma-mono' title='$msgId'>" . ($shortId !== '' ? $shortId : '—') . "</span></td>";

         echo "</tr>";
      }

      echo "</tbody></table>";
      echo "</div></div>";
   }

   /**
    * Purge orphaned records from the message_id table.
    * Removes records referencing tickets that no longer exist.
    *
    * @return int Number of records purged
    */
   public static function purgeOrphans(): int
   {
      global $DB;

      // Find message_id records where the ticket no longer exists
      $query = "DELETE m FROM `glpi_plugin_mailanalyzer_message_id` m
                LEFT JOIN `glpi_tickets` t ON m.`tickets_id` = t.`id`
                WHERE m.`tickets_id` != 0 AND t.`id` IS NULL";

      $DB->doQuery($query);
      $purged = $DB->affectedRows();

      if ($purged > 0) {
         Toolbox::logInfo("MailAnalyzer: Purged $purged orphaned message_id records");
      }

      return $purged;
   }
}
