<?php

namespace Pderas\LaravelCodeScans\Formatters;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;
use PHPStan\Command\ErrorFormatter\CiDetectedErrorFormatter;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Custom error formatter that outputs grouped categories based on rule types
 */
final class FabledErrorFormat implements ErrorFormatter
{
    public function __construct(
        private CiDetectedErrorFormatter $ci_detected_error_formatter
    )
    {
        // nothing needed other than the ci detector. It will automatically detect if it's in GitHub and use that format
    }

    public function formatErrors(AnalysisResult $analysis_result, Output $output): int
    {
        //  If in CI, use auto detected format and skip custom formatting
        $this->ci_detected_error_formatter->formatErrors($analysis_result, $output);

        $file_errors = $analysis_result->getFileSpecificErrors();
        $generic_errors = $analysis_result->getNotFileSpecificErrors();

        $has_errors = count($file_errors) + count($generic_errors);

        if (!$has_errors) {
            $output->writeLineFormatted('<fg=bright-green>[OK] No issues found</>');
            return 0;
        }
        
        $style = $output->getStyle();
        $output->writeLineFormatted('');
        $output->writeLineFormatted('<options=bold>Code Scan Results</>');

        $by_rule = [];
        foreach ($file_errors as $e) {
            $identifier = method_exists($e, 'getIdentifier') && $e->getIdentifier() !== null
                ? $e->getIdentifier()
                : 'phpstan.generic';
                
            $by_rule[$identifier] ??= [];
            $by_rule[$identifier][] = [
                'file'    => $e->getFilePath(),
                'line'    => $e->getLine(),
                'message' => $e->getMessage(),
            ];
        }

        foreach ($generic_errors as $ge) {
            $identifier = 'phpstan.generic';
            $by_rule[$identifier] ??= [];
            $by_rule[$identifier][] = [
                'file'    => '[generic]',
                'line'    => null,
                'message' => $ge,
            ];
        }

        $by_category = [];
        $category_totals = [];
        foreach ($by_rule as $identifier => $items) {
            $meta = $this->categorizeRule($identifier);
            $cat = $meta['category'];
            $by_category[$cat][$identifier] = [
                'items' => $items,
                'meta'  => $meta,
            ];
            $category_totals[$cat] = ($category_totals[$cat] ?? 0) + count($items);
        }

        $ordered_categories = [
            'Security'    => 'red',
            'Reliability' => 'yellow',
            'Performance' => 'cyan',
            'General'     => 'white'
        ];

        $total_issues = array_sum($category_totals);
        $output->writeLineFormatted('<fg=red;options=bold>Found ' . $total_issues . ' issue(s)</> across ' . count($by_rule) . ' rule(s)');

        foreach ($ordered_categories as $cat => $color) {
            if (!isset($by_category[$cat])) {
                continue;
            }
            $color = $color ?? 'white';
            $count = $category_totals[$cat] ?? 0;
            $output->writeLineFormatted('');
            $style->title('<fg=' . $color . ';options=bold>' . $cat . '</> <fg=' . $color . '>(' . $count . ')</>');

            $rules = $by_category[$cat];
            uasort($rules, function ($a, $b) {
                return count($b['items']) <=> count($a['items']);
            });
            
            foreach ($rules as $identifier => $data) {
                $label = $data['meta']['label'] ?? $identifier;
                $count_rule = count($data['items']);
                $output->writeLineFormatted(' <options=bold>' . $label . '</> <fg=' . $color . '>(' . $count_rule . ')</>  <fg=blue>' . $identifier . '</>');
                $output_results = [];
                foreach ($data['items'] as $idx => $item) {
                    $file = strstr($item['file'], "app/");
                    $line = $item['line'];
                    $url = str_replace(
						['%file%', '%relFile%', '%line%'],
						[$item['file'], $file, (string) $line],
						"vscode://file/%%file%%:%%line%%",
					);
                    $output_results[] = [(string) $line, '<href=' . OutputFormatter::escape($url) . '><fg=cyan>' . $file . '</></>' . "\n" . $item['message'] . ($idx < (count($data['items']) - 1) ? "\n" : "")];
                }
                $style->table(['Line', 'Error'], $output_results);
            }
        }

        $output->writeLineFormatted('');
        $output->writeLineFormatted(
            'Summary: '
            . 'Security ' . ($category_totals['Security'] ?? 0)
            . ', Reliability ' . ($category_totals['Reliability'] ?? 0)
            . ', Performance ' . ($category_totals['Performance'] ?? 0)
            . ', General ' . ($category_totals['General'] ?? 0)
        );

        return (int) $has_errors > 0;
    }

    /**
     * Map a rule identifier to a category, severity, and label.
     */
    private function categorizeRule(string $identifier): array
    {
        if (str_starts_with($identifier, 'pderas.disallow.function.')) {
            $fn = substr($identifier, strlen('pderas.disallow.function.'));
            return ['category' => 'Security', 'severity' => 'error', 'label' => 'Banned function ' . $fn . '()'];
        }
        if ($identifier === 'pderas.disallow.exit') {
            return ['category' => 'Security', 'severity' => 'error', 'label' => 'Disallowed exit/die'];
        }
        if ($identifier === 'pderas.disallow.debug') {
            return ['category' => 'Security', 'severity' => 'error', 'label' => 'Disallowed debug helper'];
        }
        if (str_starts_with($identifier, 'missingType.') || str_starts_with($identifier, 'phpstan.missingType.')) {
            return ['category' => 'Reliability', 'severity' => 'warning', 'label' => 'Missing type'];
        }
        if (str_starts_with($identifier, 'deadCode.')) {
            return ['category' => 'Reliability', 'severity' => 'warning', 'label' => 'Dead code'];
        }
        if (str_starts_with($identifier, 'deprecated.')) {
            return ['category' => 'Reliability', 'severity' => 'warning', 'label' => 'Deprecated usage'];
        }
        if (str_starts_with($identifier, 'property.') || str_starts_with($identifier, 'array.') || str_starts_with($identifier, 'arguments.')) {
            return ['category' => 'Reliability', 'severity' => 'warning', 'label' => 'Deprecated usage'];
        }
        if (str_starts_with($identifier, 'performance.')) {
            return ['category' => 'Performance', 'severity' => 'warning', 'label' => 'Performance issue'];
        }

        return ['category' => 'General', 'severity' => 'warning', 'label' => $identifier];
    }
}