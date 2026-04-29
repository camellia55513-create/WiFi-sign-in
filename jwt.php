<?php

class JWT
{
    // 使用完全相同的密钥
    private static $secretKey = 'class_checkin_secret_key_2024';
    private static $algo = 'HS256';

    /**
     * 生成 JWT
     */
    public static function generateJWT($payload)
    {
        $header = json_encode(['alg' => self::$algo, 'typ' => 'JWT']);
        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode(json_encode($payload));
        
        // 生成签名 - 注意这里用 . 连接
        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, self::$secretKey, true);
        $base64Signature = self::base64UrlEncode($signature);
        
        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }

    /**
     * 验证 JWT -
     */
    public static function verifyJWT($token)
    {
        // 记录原始token
        error_log("[JWT验证] 原始token: " . substr($token, 0, 50) . "...");
        
        // 清理token
        $token = trim($token);
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
            $token = trim($token);
            error_log("[JWT验证] 清理后token: " . substr($token, 0, 50) . "...");
        }
        
        // 分割token
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            error_log("[JWT验证] 失败：token格式错误，部分数=" . count($parts));
            return false;
        }
        
        list($base64Header, $base64Payload, $base64Signature) = $parts;
        
        // 解码payload）
        $payloadJson = self::base64UrlDecode($base64Payload);
        $payload = json_decode($payloadJson, true);
        
        error_log("[JWT验证] payload内容: " . $payloadJson);
        
        if (!$payload) {
            error_log("[JWT验证] 失败：payload解码失败");
            return false;
        }
        
        // 重新计算签名验证
        $expectedSignature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, self::$secretKey, true);
        $actualSignature = self::base64UrlDecode($base64Signature);
        
        $expectedBase64 = self::base64UrlEncode($expectedSignature);
        $actualBase64 = self::base64UrlEncode($actualSignature);
        
        error_log("[JWT验证] 期望签名: " . $expectedBase64);
        error_log("[JWT验证] 实际签名: " . $actualBase64);
        
        // 验证签名
        if ($expectedBase64 !== $actualBase64) {
            error_log("[JWT验证] 失败：签名不匹配");
            error_log("[JWT验证] 使用的密钥: " . self::$secretKey);
            return false;
        }
        
        // 检查过期
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            error_log("[JWT验证] 失败：token过期");
            return false;
        }
        
        error_log("[JWT验证] 成功！用户ID: " . ($payload['uid'] ?? 'unknown'));
        return $payload;
    }

    /**
     * 从请求头获取token -
     */
    public static function getTokenFromHeaders()
    {
        $headers = getallheaders();
        $authHeader = '';
        
        // 记录所有headers用于调试
        error_log("[getTokenFromHeaders] 所有headers: " . json_encode($headers));
        
        // 尝试获取Authorization header
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            error_log("[getTokenFromHeaders] 从getallheaders获取: " . $authHeader);
        } elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
            error_log("[getTokenFromHeaders] 从getallheaders(小写)获取: " . $authHeader);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            error_log("[getTokenFromHeaders] 从HTTP_AUTHORIZATION获取: " . $authHeader);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            error_log("[getTokenFromHeaders] 从REDIRECT_HTTP_AUTHORIZATION获取: " . $authHeader);
        }
        
        // 提取Bearer token
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
            error_log("[getTokenFromHeaders] 提取到token: " . substr($token, 0, 30) . "...");
            return $token;
        }
        
        error_log("[getTokenFromHeaders] 没有找到有效的token");
        return '';
    }

    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
?>