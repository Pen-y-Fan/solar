# Solis API PoC

I have the keys and Url to the Solis API. I want to create a PoC to test the API.

## The PoC

Create a config file solis.php which will accept the API keys:

- KEY_ID
- KEY_SECRET
- API_URL

Add the keys to the `.env.example`

Create a console command which will call an Action direct (similar to how `app/Console/Commands/Forecast.php` except call the action direct instead of using the command `RequestSolcastForecast` which calls the action `app/Domain/Forecasting/Actions/ForecastAction.php`.)

Write the Action to call the API, see the example below for Authorisation; the code is Java, so convert to PHP methods to encode using MD5. The endpoint `/v1/api/inverterList`

See an example request, taken from the documentation, below.

Once the response is received, log the response to the log file.

Return a success message to the console.

Only create two files, the console command and the Action. You are free to choose a name of the command and action.

## Authorisation

https://ginlong-product.oss-cn-shanghai.aliyuncs.com/templet/Authorization.java

```java
package com.ginlong;

import com.alibaba.fastjson.JSON;
import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Response;
import org.apache.commons.codec.binary.Base64;

import javax.crypto.Mac;
import javax.crypto.SecretKey;
import javax.crypto.spec.SecretKeySpec;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.text.SimpleDateFormat;
import java.util.*;

public class Authorization {
    public static void main(String[] args) {
        try {
            String key = "";
            String keySecret = "";
            Map<String,Object> map = new HashMap();
            map.put("pageNo",1);
            map.put("pageSize",10);
            String body = JSON.toJSONString(map);
            String ContentMd5 = getDigest(body);
            String Date = getGMTTime();
            String path = "/v1/api/userStationList";
            String param = "POST" + "\n" + ContentMd5 + "\n" + "application/json" + "\n" + Date + "\n" + path;
            String sign = HmacSHA1Encrypt(param, keySecret);
            String url = "url" + path ;
            OkHttpClient client = new OkHttpClient();
            MediaType xmlType = MediaType.parse("application/json;charset=UTF-8");
            okhttp3.RequestBody requestBody = okhttp3.RequestBody.create(xmlType,body);
            okhttp3.Request request = new okhttp3.Request.Builder()
                    .url(url)
                    .addHeader("Content-type", "application/json;charset=UTF-8")
                    .addHeader("Authorization","API "+key+":"+sign)
                    .addHeader("Content-MD5",ContentMd5)
                    .addHeader("Date",Date)
                    .post(requestBody)
                    .build();
            Response response = client.newCall(request).execute();
            String string = response.body().string();
            System.out.println(string);

        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    public static String HmacSHA1Encrypt(String encryptText, String KeySecret) throws Exception
    {
        byte[] data=KeySecret.getBytes("UTF-8");
        //æ ¹æ®ç»™å®šçš„å­—èŠ‚æ•°ç»„æž„é€ ä¸€ä¸ªå¯†é’¥,ç¬¬äºŒå‚æ•°æŒ‡å®šä¸€ä¸ªå¯†é’¥ç®—æ³•çš„åç§°
        SecretKey secretKey = new SecretKeySpec(data, "HmacSHA1");
        //ç”Ÿæˆä¸€ä¸ªæŒ‡å®š Mac ç®—æ³• çš„ Mac å¯¹è±¡
        Mac mac = Mac.getInstance("HmacSHA1");
        //ç”¨ç»™å®šå¯†é’¥åˆå§‹åŒ– Mac å¯¹è±¡
        mac.init(secretKey);

        byte[] text = encryptText.getBytes("UTF-8");
        //å®Œæˆ Mac æ“ä½œ
        byte[] result = mac.doFinal(text);
        return Base64.encodeBase64String(result);
    }

    public static String  getGMTTime(){

        Calendar cd = Calendar.getInstance();
        SimpleDateFormat sdf = new SimpleDateFormat("EEE, d MMM yyyy HH:mm:ss 'GMT'", Locale.US);
        sdf.setTimeZone(TimeZone.getTimeZone("GMT")); // è®¾ç½®æ—¶åŒºä¸ºGMT
        String str = sdf.format(cd.getTime());
        return  str;
    }

    public static String getDigest(String test) {
        String result = "";
        try {
            MessageDigest md = MessageDigest.getInstance("MD5");
            md.update(test.getBytes());
            byte[] b = md.digest();
            result = java.util.Base64.getEncoder().encodeToString(b);
        } catch (NoSuchAlgorithmException e) {
            e.printStackTrace();
        }
        return result;
    }
}
```
## Solis documentation

Extract of the Solis documentation

## 1 GLOBAL DESCRIPTION

1) All interface encryption is based on the HTTPS protocol.
2) The update frequency for all interface data is 5 minutes.
3) All interface request methods are POST.
4) All interface request types are application/JSON;Charset=UTF-8.
5) All interface requests require adding Content MD5, Content Type, Date, and Authorization to the header.
6) All interface returned data is in JSON format.
7) All interface returned data (power, energy, energy, frequency, etc.) must be used in conjunction with the
   unit.

## 3 DEVICE INTERFACES

3.1 Obtain the inverter list under the account

3.1 Obtain the inverter list under the account
Interface Name Obtain the inverter list under the account
Interface Description
Corresponding to the SolisCloud platform device overview - inverter list, a
single call can obtain the list data of up to 100 devices.
Request URL https://www.soliscloud.com:13333/v1/api/inverterList
Interface frequency
limit
2 times/sec
Request parameters [Body]
Parameter Name Data Type Required Description
pageNo String Y
Specify the number of page numbers to return. The
default value is 1, representing page 1.
pageSize String Y
Specify the number of returns per page. The d

Return parameters [Body]
Parameter Name Data Type Required Description
code String Y
0 represents success, while others represent failure.
The failure code is detailed in Appendix 1.
msg String Y Description of code values
data Object Y Data identification
page Object Y Result list
inverterStatusV o Object Y Number of results


## Example Request

```text
POST /v1/api/inverterList
Connection: keep-alive
Date: Tue, 27 Jun 2023 06:23:30 GMT
Content-MD5: Trz24rS60t0X3mHzTjNPww==
Authorization:API
1300386381676644416:h8wgh|L2V9593N8AjNMSNRZmB8=
Content-Type: application/json;charset=UTF-8
Content-Length: 84
Host: test.soliscloud.com:3333
User-Agent: Apache-HttpClient/4.5.13 (Java/11.0.17)
{
    "pageNo": 1,
    "pageSize": 10,
    "stationId": "1298491919448631809",
    "nmiCode": "41028459350"
}
```
