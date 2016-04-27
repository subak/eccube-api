<?php

namespace Plugin\EccubeApi\Repository\OAuth2;

use Doctrine\ORM\EntityRepository;
use Plugin\EccubeApi\Entity\OAuth2\AuthorizationCode;
use OAuth2\Storage\AuthorizationCodeInterface;
use OAuth2\OpenID\Storage\AuthorizationCodeInterface as OpenIDAuthorizationCodeInterface;

/**
 * AuthorizationCodeRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 *
 * @author Kentaro Ohkouchi
 * @link http://bshaffer.github.io/oauth2-server-php-docs/cookbook/doctrine2/
 */
class AuthorizationCodeRepository extends EntityRepository implements AuthorizationCodeInterface, OpenIDAuthorizationCodeInterface
{
    /**
     * コードを指定して Authorization code のフィールドの配列を取得します.
     *
     * @param string $code コードの文字列
     * @return array Authorization code のフィールドの配列
     */
    public function getAuthorizationCode($code)
    {
        $authCode = $this->findOneBy(array('code' => $code));
        if ($authCode && $authCode->getExpires()->getTimestamp() >= time()) {
            $authCode = $authCode->toArray();
            if (is_object($authCode['client'])) {
                $authCode['client_id'] = $authCode['client']->getClientIdentifier();
            }
            if (is_object($authCode['user'])) {
                $authCode['user_id'] = $authCode['user']->getId();
            }
            $authCode['expires'] = $authCode['expires']->getTimestamp();
        }
        return $authCode;
    }

    /**
     * AuthorizatoinCode を生成して保存します.
     *
     * 第3引数の $user_id は、 UserInfo::sub の文字列が渡ってくることに注意します.
     *
     * @param string $code コードの文字列
     * @param string $clientIdentifier client_id 文字列
     * @param string $user_id UserInfo::sub
     * @param string $redirectUri redirect_uri
     * @param integer $expires 有効期限の UNIX タイムスタンプ
     * @param string $scope 認可された scope. スペース区切りで複数指定可能
     * @param string $id_token OpenID Connect ID token
     * @return void
     */
    public function setAuthorizationCode($code, $clientIdentifier, $user_id, $redirectUri, $expires, $scope = null, $id_token = null)
    {
        $client = $this->_em->getRepository('Plugin\EccubeApi\Entity\OAuth2\Client')
            ->findOneBy(
                array('client_identifier' => $clientIdentifier)
            );
        $user = $this->_em->getRepository('Plugin\EccubeApi\Entity\OAuth2\OpenID\UserInfo')
            ->findOneBy(
                array('sub' => $user_id)
            );
        $AuthorizationCode = $this->_em->getRepository('Plugin\EccubeApi\Entity\OAuth2\AuthorizationCode')
            ->findOneBy(
                array('code' => $code)
            );

        $now = new \DateTime();
        if ($AuthorizationCode) {
            $AuthorizationCode->setPropertiesFromArray(
                array(
                    'code' => $code,
                    'client' => $client,
                    'user' => $user,
                    'redirect_uri' => $redirectUri,
                    'expires' => $now->setTimestamp($expires),
                    'scope' => $scope,
                    'id_token' => $id_token,
                )
            );
        } else {
            $AuthorizationCode = new \Plugin\EccubeApi\Entity\OAuth2\AuthorizationCode();
            $AuthorizationCode->setPropertiesFromArray(
                array(
                    'code' => $code,
                    'client' => $client,
                    'user' => $user,
                    'redirect_uri' => $redirectUri,
                    'expires' => $now->setTimestamp($expires),
                    'scope' => $scope,
                    'id_token' => $id_token,
                )
            );
            $this->_em->persist($AuthorizationCode);
        }

        $this->_em->flush($AuthorizationCode);
    }

    /**
     * 期限切れとなった AuthorizationCode を削除します.
     *
     * @param string $code コードの文字列
     * @return void
     */
    public function expireAuthorizationCode($code)
    {
        $authCode = $this->findOneBy(array('code' => $code));
        if ($authCode && $authCode->getExpires()->getTimestamp() <= time()) {
            $this->_em->remove($authCode);
            $this->_em->flush($authCode);
        }
    }
}
