<?php
/**
 * Created by PhpStorm.
 * User: andreicotaga
 * Date: 2019-03-22
 * Time: 10:28
 */

require_once DIR_SYSTEM . 'library/retargeting/vendor/autoload.php';

class ControllerApiRetargetingtracker extends Controller
{
    const CUSTOMERS_OFFSET = 20;

    /**
     * @throws Exception
     */
    public function customers()
    {
        try {

            if(isset($this->request->post['token']))
            {
                if(isset($this->request->post['page']))
                {
                    $page = $this->request->post['page'];
                }
                elseif(isset($this->request->get['page']))
                {
                    $page = $this->request->get['page'];
                }
                else
                {
                    $page = 1;
                }

                $params = [
                    'start' => $page * self::CUSTOMERS_OFFSET - self::CUSTOMERS_OFFSET,
                    'limit' => $page * self::CUSTOMERS_OFFSET
                ];

                $customers = (array)$this->getCustomers($params);

                if(is_array($customers) && !empty($customers))
                {
                    $encrypt = new \RetargetingSDK\Helpers\EncryptionHelper($this->request->post['token']);

                    $countCustomers = count($customers);

                    $customersApi = new \RetargetingSDK\Api\Customers($this->request->post['token']);

                    foreach($customers as $customer)
                    {
                        $parsedCustomers[] = $encrypt->encrypt(json_encode([
                            'firstName' => $customer['firstname'],
                            'lastName' => $customer['lastname'],
                            'email' => $customer['email'],
                            'phone' => $customer['telephone'],
                            'status' => $customer['status'] === '1'
                        ]));
                    }

                    $customersApi->setData($parsedCustomers);
                    $customersApi->setCurrentPage((int)$page);
                    $customersApi->setLastPage(
                        round($countCustomers/self::CUSTOMERS_OFFSET) > 1 ? round($countCustomers/self::CUSTOMERS_OFFSET) : 1);

                    $customersApi->setPrevPage($page > 2 ? $this->request->get['route'] . '?page=' . (string)($page - 1) : $this->request->get['route'] . '?page=' . (string)1);
                    $customersApi->setNextPage($page >= 1 && round($countCustomers/self::CUSTOMERS_OFFSET) > 1 ? $this->request->get['route'] . '?page=' . (string)($page + 1) : $this->request->get['route'] . '?page=' . (string)1);

                    echo $customersApi->prepareCustomersApiInfo();
                }

                echo json_encode([]);
            }
            else
            {
                throw new Exception('No token key provided as POST parameter!');
            }
        }
        catch (Exception $exception)
        {
            echo $exception->getMessage();
        }
    }

    /**
     * Get customers from DB
     * @param $params
     * @return mixed
     */
    private function getCustomers($params)
    {
        $sql = "SELECT * FROM " . DB_PREFIX . "customer c  WHERE c.status = 1";

        if (isset($params['start']) || isset($params['limit'])) {
            if ($params['start'] < 0) {
                $params['start'] = 0;
            }

            if ($params['limit'] < 1) {
                $params['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$params['start'] . "," . (int)$params['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }
}