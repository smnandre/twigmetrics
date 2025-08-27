<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class TreeDirectoryFormatter
{
    /**
     * Convert flat directory paths into hierarchical tree structure.
     *
     * @param array<array<string, mixed>> $directories Array of directory data with 'path' key
     *
     * @return array<array<string, mixed>> Directory data with 'tree_path' and 'indent_level' added
     */
    public function formatAsTree(array $directories): array
    {
        if (empty($directories)) {
            return [];
        }

        $paths = array_map(fn ($dir) => rtrim((string) ($dir['path'] ?? ''), '/'), $directories);
        sort($paths);

        $tree = [];
        $pathMap = [];

        foreach ($directories as $dir) {
            $cleanPath = rtrim((string) ($dir['path'] ?? ''), '/');
            $pathMap[$cleanPath] = $dir;
        }

        $processedPaths = [];

        foreach ($paths as $path) {
            $parts = explode('/', $path);
            $depth = count($parts);

            $indentLevel = $depth - 1;
            $indent = str_repeat('  ', $indentLevel);

            $isLastChild = true;
            if ($indentLevel > 0) {
                $parentPath = implode('/', array_slice($parts, 0, -1));
                $siblingPaths = array_filter($paths, function ($p) use ($parentPath, $depth) {
                    $pParts = explode('/', $p);

                    return count($pParts) === $depth
                           && implode('/', array_slice($pParts, 0, -1)) === $parentPath;
                });

                $sortedSiblings = array_values($siblingPaths);
                $isLastChild = (end($sortedSiblings) === $path);
            }

            $treePrefix = 0 === $indentLevel ? '├─ ' : ($isLastChild ? '└─ ' : '├─ ');

            $displayName = $indentLevel > 0 ? basename($path) : $path;

            $originalData = $pathMap[$path];
            $originalData['tree_path'] = $indent.$treePrefix.$displayName.'/';
            $originalData['indent_level'] = $indentLevel;
            $originalData['display_name'] = $displayName;
            $originalData['full_path'] = $path;

            $tree[] = $originalData;
        }

        return $tree;
    }

    /**
     * Calculate the optimal width for tree paths with minimal truncation.
     *
     * @param array<array<string, mixed>> $treeDirectories
     * @param int                         $availableWidth  Available width for the tree path column
     *
     * @return int Optimal width for tree paths
     */
    public function calculateTreePathWidth(array $treeDirectories, int $availableWidth = 25): int
    {
        if (empty($treeDirectories)) {
            return $availableWidth;
        }

        $maxTreePathLength = 0;

        foreach ($treeDirectories as $dir) {
            $treePath = (string) ($dir['tree_path'] ?? '');
            $maxTreePathLength = max($maxTreePathLength, mb_strlen($treePath));
        }

        return min($maxTreePathLength, $availableWidth);
    }
}
