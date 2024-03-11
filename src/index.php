<?php

/**
 * Code Assessment - Bamboo
 *
 * @position: Senior Full-Stack Laravel PHP Backend Developer
 * @author Vladimir Stamenkovic <vladimir@techflyt.se>
 *
 * @note: Certain, modern, PHP type-hints were not used due to the PHP version requirement (7.3+)
 */

if (!function_exists('dd')) {

    /**
     * Debug helper
     *
     * @param mixed $input Data to output
     * @param bool $die Whether to die after output
     * @return void
     */
    function dd($input, bool $die = true)
    {

        $output = '<pre>' . print_r($input, true) . '</pre>';

        if ($die) {
            exit($output);
        }

        echo $output;
    }
}

abstract class RemoteModel
{

    /**
     * Parsed JSON
     *
     * @var array
     */
    protected $data = [];

    /**
     * Get URL of JSON (API)
     */
    abstract protected function getUrl(): string;

    /**
     * @throws \Exception
     */
    public function __construct()
    {

        $ch = curl_init($this->getUrl());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $result = curl_exec($ch);

        if (curl_error($ch)) {
            throw new Exception (sprintf("Failed to fetch remote data: %s", curl_error($ch)));
        }

        curl_close($ch);

        $this->data = json_decode($result);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function debug(): void
    {
        dd($this->data);
    }
}

class Travel extends RemoteModel
{

    /**
     * Source (API) Url
     *
     * @return string
     */
    protected function getUrl(): string
    {
        return 'https://5f27781bf5d27e001612e057.mockapi.io/webprovise/travels';
    }
}

class Company extends RemoteModel
{

    /**
     * Source (API) Url
     *
     * @return string
     */
    protected function getUrl(): string
    {
        return 'https://5f27781bf5d27e001612e057.mockapi.io/webprovise/companies';
    }
}

class TestScript
{

    /**
     * @param array $uuids Children UUIDs
     * @param array $companies Unique list of (not yet processed) companies
     * @param array $mappings Parent -> Children mapping
     * @param array $ignored Processed companies
     * @return array
     */
    private function craftChildrenTree(array $uuids, array &$companies, array &$mappings, array &$ignored = []): array
    {

        $cost = 0;
        $tree = [];

        foreach ($uuids as $uuid) {

            $item = $companies[$uuid];

            if ($childrenUuids = $mappings[$uuid] ?? []) {

                /**
                 * Handle children of a children [of a children...]
                 */

                [$cost, $children] = $this->craftChildrenTree($childrenUuids, $companies, $mappings, $ignored);

                $item['cost'] += $cost;
                $item['children'] = $children;

                unset($mappings[$uuid]);
            }

            $tree[] = $item;
            $cost += $item['cost'];

            /**
             * Neither requirements nor dataset suggest that company that is already sub-company (child)
             * should exist (be counted) on its own. Therefore, remove it from the list.
             */

            $ignored[] = $uuid;
        }

        return [$cost, $tree];
    }

    public function execute()
    {

        $start = microtime(true);

        $results = [];
        $mappings = [];
        $companies = [];

        /**
         * Craft unique array of companies as well as $mappings array (containing
         * a list of companies with their children (if they have them)).
         */

        foreach ((new Company())->getData() as $company) {

            $companies[$company->id] = array_merge((array)$company, [
                'cost' => 0,
                'children' => []
            ]);

            if ($parentId = $company->parentId) {

                if (!isset($mappings[$parentId])) {
                    $mappings[$parentId] = [];
                }

                $mappings[$parentId][] = $company->id;
            }

        }

        /**
         * Calculate costs for each individual company
         */

        foreach ((new Travel())->getData() as $travel) {

            if (!isset($companies[$travel->companyId])) {
                continue; // something's wrong with data set, ignore...
            }

            $companies[$travel->companyId]['cost'] += $travel->price;
        }

        /**
         * Create a tree & append prices to parents
         */

        $subCompanies = [];
        foreach ($companies as $uuid => $company) {

            if (in_array($uuid, $subCompanies)) {

                continue; // child of another company, ignore
            } else if (!$childrenUuids = $mappings[$uuid] ?? []) {

                $results[] = $company;

                continue; // no children, move on
            }

            [$cost, $children] = $this->craftChildrenTree($childrenUuids, $companies, $mappings, $subCompanies);

            $company['cost'] += $cost;
            $company['children'] = $children;

            $results[] = $company;
        }

        echo json_encode($results) . "\n\n";

        /**
         * JSON Output (as requested in the task description)
         *
         * [{"id":"uuid-1","createdAt":"2021-02-26T00:55:36.632Z","name":"Webprovise Corp","parentId":"0","cost":68435,"children":[{"id":"uuid-2","createdAt":"2021-02-25T10:35:32.978Z","name":"Stamm LLC","parentId":"uuid-1","cost":5199,"children":[{"id":"uuid-4","createdAt":"2021-02-25T06:11:47.519Z","name":"Price and Sons","parentId":"uuid-2","cost":1340,"children":[]},{"id":"uuid-7","createdAt":"2021-02-25T07:56:32.335Z","name":"Zieme - Mills","parentId":"uuid-2","cost":1636,"children":[]},{"id":"uuid-19","createdAt":"2021-02-25T21:06:18.777Z","name":"Schneider - Adams","parentId":"uuid-2","cost":794,"children":[]}]},{"id":"uuid-3","createdAt":"2021-02-25T15:16:30.887Z","name":"Blanda, Langosh and Barton","parentId":"uuid-1","cost":15713,"children":[{"id":"uuid-5","createdAt":"2021-02-25T13:35:57.923Z","name":"Hane - Windler","parentId":"uuid-3","cost":1288,"children":[]},{"id":"uuid-6","createdAt":"2021-02-26T01:41:06.479Z","name":"Vandervort - Bechtelar","parentId":"uuid-3","cost":2512,"children":[]},{"id":"uuid-9","createdAt":"2021-02-25T16:02:49.099Z","name":"Kuhic - Swift","parentId":"uuid-3","cost":3086,"children":[]},{"id":"uuid-17","createdAt":"2021-02-25T11:17:52.132Z","name":"Rohan, Mayer and Haley","parentId":"uuid-3","cost":4072,"children":[]},{"id":"uuid-20","createdAt":"2021-02-26T01:51:25.421Z","name":"Kunde, Armstrong and Hermann","parentId":"uuid-3","cost":908,"children":[]}]},{"id":"uuid-8","createdAt":"2021-02-25T23:47:57.596Z","name":"Bartell - Mosciski","parentId":"uuid-1","cost":33893,"children":[{"id":"uuid-10","createdAt":"2021-02-26T01:39:33.438Z","name":"Lockman Inc","parentId":"uuid-8","cost":4288,"children":[]},{"id":"uuid-11","createdAt":"2021-02-26T00:32:01.307Z","name":"Parker - Shanahan","parentId":"uuid-8","cost":12236,"children":[{"id":"uuid-12","createdAt":"2021-02-25T06:44:56.245Z","name":"Swaniawski Inc","parentId":"uuid-11","cost":2110,"children":[]},{"id":"uuid-14","createdAt":"2021-02-25T15:22:08.098Z","name":"Weimann, Runolfsson and Hand","parentId":"uuid-11","cost":7254,"children":[]}]},{"id":"uuid-13","createdAt":"2021-02-25T20:45:53.518Z","name":"Balistreri - Bruen","parentId":"uuid-8","cost":1686,"children":[]},{"id":"uuid-15","createdAt":"2021-02-25T18:00:26.864Z","name":"Predovic and Sons","parentId":"uuid-8","cost":4725,"children":[]},{"id":"uuid-16","createdAt":"2021-02-26T01:50:50.354Z","name":"Weissnat - Murazik","parentId":"uuid-8","cost":3277,"children":[]}]},{"id":"uuid-18","createdAt":"2021-02-26T02:31:22.154Z","name":"Walter, Schmidt and Osinski","parentId":"uuid-1","cost":2033,"children":[]}]}]
         */

        echo 'Total time: ' . (microtime(true) - $start);
    }
}

(new TestScript())->execute();
