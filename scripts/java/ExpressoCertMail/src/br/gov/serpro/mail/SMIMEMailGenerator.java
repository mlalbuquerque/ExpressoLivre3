/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
package br.gov.serpro.mail;

import br.gov.serpro.cert.DigitalCertificate;
import com.sun.mail.util.BASE64EncoderStream;
import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.UnsupportedEncodingException;
import java.net.URI;
import java.net.URISyntaxException;
import java.security.KeyManagementException;
import java.security.NoSuchAlgorithmException;
import java.security.cert.CertificateException;
import java.security.cert.X509Certificate;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import javax.activation.DataHandler;
import javax.mail.Message;
import javax.mail.MessagingException;
import javax.mail.Part;
import javax.mail.Session;
import javax.mail.internet.*;
import javax.mail.util.ByteArrayDataSource;
import javax.net.ssl.*;
import net.htmlparser.jericho.Renderer;
import net.htmlparser.jericho.Source;
import org.apache.http.HttpEntity;
import org.apache.http.HttpResponse;
import org.apache.http.client.CookieStore;
import org.apache.http.client.methods.HttpGet;
import org.apache.http.conn.ClientConnectionManager;
import org.apache.http.conn.scheme.Scheme;
import org.apache.http.conn.scheme.SchemeRegistry;
import org.apache.http.conn.ssl.SSLSocketFactory;
import org.apache.http.conn.ssl.X509HostnameVerifier;
import org.apache.http.impl.client.DefaultHttpClient;
import org.apache.http.impl.cookie.BasicClientCookie;
import org.apache.http.params.CoreProtocolPNames;
import sun.reflect.generics.reflectiveObjects.NotImplementedException;

/**
 *
 * @author 75779242020
 */
public class SMIMEMailGenerator {
    
    private static List<Part> _getAttachments(List<Map<String, Object>> attachmentsRef, String userAgent) throws
            URISyntaxException, UnsupportedEncodingException, IOException, NoSuchAlgorithmException,
            KeyManagementException, MessagingException
    {
        List<Part> attachments = new ArrayList<Part>(attachmentsRef.size());

        for (Map<String, Object> attach : attachmentsRef)
        {
            Map<String, Object> tempFile= (Map<String, Object>) attach.get("tempFile");
            String id = (String) tempFile.get("id");
            String session_id = (String) tempFile.get("session_id");

            System.out.println("file id: "+id);
            System.out.println("file session_id: "+session_id);
            String name = (String) tempFile.get("name");

            // This method will not be implemented here, so we'll get this URL/URI from Applet object
            // TODO: don't activate debug
            URI url = new URI("https://localhost:4433/index.php?XDEBUG_SESSION_START=netbeans-xdebug&method=Tinebase.getTempFile&id="+id);

//                List<NameValuePair> params = new ArrayList<NameValuePair>();
//                params.add(new BasicNameValuePair("method", "Tinebase.getTempFile"));
//                params.add(new BasicNameValuePair("id", id));
//                params.add(new BasicNameValuePair("session_id", session_id));
//                params.add(new BasicNameValuePair("path", path));
//                params.add(new BasicNameValuePair("name", name));
//                params.add(new BasicNameValuePair("type", type));
//                params.add(new BasicNameValuePair("size", size.toString()));

            // Process download and initialize part
            // Instancia o HttpClient
            // TODO: Define new system keystore
            DefaultHttpClient client = new DefaultHttpClient();
            client.getParams().setParameter(CoreProtocolPNames.USER_AGENT, userAgent);
            
            String sessionCookieName = "TINE20SESSID";
            CookieStore cookieStore = client.getCookieStore();
            BasicClientCookie tine20SessId = new BasicClientCookie(sessionCookieName, session_id);
            tine20SessId.setDomain("localhost");
            tine20SessId.setPath("/");
            BasicClientCookie xdebug = new BasicClientCookie("XDEBUG_SESSION", "netbeans-xdebug");
            xdebug.setDomain("localhost");
            xdebug.setPath("/");
            cookieStore.addCookie(tine20SessId);
            cookieStore.addCookie(xdebug);

            // TODO: Use the default ssl config
//                javax.net.ssl.SSLSocketFactory defaultSF = (javax.net.ssl.SSLSocketFactory) javax.net.ssl.SSLSocketFactory.getDefault();
//                SSLSocketFactory socketFactory = new SSLSocketFactory(defaultSF, SSLSocketFactory.ALLOW_ALL_HOSTNAME_VERIFIER);
//                Scheme sch = new Scheme("https", 4433, socketFactory);
//                client.getConnectionManager().getSchemeRegistry().register(sch);


            X509TrustManager tm = new X509TrustManager() {

                @Override
                public void checkClientTrusted(X509Certificate[] xcs, String string) throws CertificateException {
                }

                @Override
                public void checkServerTrusted(X509Certificate[] xcs, String string) throws CertificateException {
                }

                @Override
                public X509Certificate[] getAcceptedIssuers() {
                    return null;
                }
            };
            X509HostnameVerifier verifier = new X509HostnameVerifier() {

                @Override
                public void verify(String string, SSLSocket ssls) throws IOException {
                }

                @Override
                public void verify(String string, X509Certificate xc) throws SSLException {
                }

                @Override
                public void verify(String string, String[] strings, String[] strings1) throws SSLException {
                }

                @Override
                public boolean verify(String string, SSLSession ssls) {
                    return true;
                }
            };

            SSLContext ctx = SSLContext.getInstance("TLS");
            ctx.init(null, new TrustManager[]{tm}, null);
            SSLSocketFactory ssf = new SSLSocketFactory(ctx, verifier);
            ClientConnectionManager ccm = client.getConnectionManager();
            SchemeRegistry sr = ccm.getSchemeRegistry();
            sr.register(new Scheme("https", 4433, ssf));

            // Configura um Post request
            HttpGet get = new HttpGet(url);

            HttpResponse response = client.execute(get);
            HttpEntity httpEntity = response.getEntity();


            // Unless content-type is text/html or text/plain we'll set header
            // Content-Transfer-Encoding as base64.
            Part part = new MimeBodyPart();
            String contentType = httpEntity.getContentType().getValue();

            ByteArrayOutputStream bos = new ByteArrayOutputStream();
            BASE64EncoderStream base64Encoder = new BASE64EncoderStream(bos, 76);
            httpEntity.writeTo(base64Encoder);

            // Todo: we should base64 encode content here?
            part.setDataHandler(new DataHandler(new ByteArrayDataSource(bos.toByteArray(), contentType)));
            part.setHeader("Content-Type", contentType);
            part.setDisposition("attachment");
            part.setFileName(MimeUtility.encodeText(name, "UTF-8", null));
            part.setHeader("Content-Transfer-Encoding", "base64");

            attachments.add(part);

            // TODO: resolv security checking, probably properties.

        }

        return attachments;
    }
    
    private static MimeBodyPart _buildUnsignedBodyPart(String body, String contentType, List<Part> attachments) throws MessagingException, UnsupportedEncodingException, IOException{
            
        MimeBodyPart completeBody = new MimeBodyPart();
        MimeMultipart mm = null;
        Part firstPart = new MimeBodyPart();

        // TODO: String body = processEmbbedImages();
        // todo: boolean property sending_plain defines if we have a multipart/alternative 
        // or text/plain as messageBody.
        MimeMultipart bodyContent = new MimeMultipart("alternative");

        Part textPlainPart = new MimeBodyPart();

        Source bodyHtmlSource = new Source(body);
        String textPlainBody = new Renderer(bodyHtmlSource).toString();
        textPlainPart.setDataHandler(new DataHandler(new ByteArrayDataSource(textPlainBody, "text/plain")));
        textPlainPart.setHeader("Content-Type", "text/plain; charset=UTF-8");
        textPlainPart.setHeader("Content-Transfer-Encoding", "quoted-printable");

        Part textHtmlPart = new MimeBodyPart();

        textHtmlPart.setDataHandler(new DataHandler(new ByteArrayDataSource(bodyHtmlSource.toString(), contentType)));
        textHtmlPart.setHeader("Content-Type", contentType+"; charset=UTF-8");
        textHtmlPart.setHeader("Content-Transfer-Encoding", "quoted-printable");

        bodyContent.addBodyPart((MimeBodyPart) textPlainPart);
        bodyContent.addBodyPart((MimeBodyPart) textHtmlPart);

        firstPart.setContent(bodyContent);

        // use MimeUtility.encode with a ByteArrayOutputStream

        if (!attachments.isEmpty())
        {
            mm = new MimeMultipart("mixed");
            mm.addBodyPart((MimeBodyPart) firstPart);
            for (Part attach : attachments)
            {
                mm.addBodyPart((MimeBodyPart) attach);
            }

            completeBody.setContent(mm);
        }
        else
        {
            completeBody.setContent(bodyContent);
        }

        return completeBody;
    }
    
    private static MimeMessage _buildMimeMessage(MimeBodyPart messageBody, Map<String, Object> model) throws IOException, MessagingException{
        
        MimeMessage message = new MimeMessage(Session.getInstance(System.getProperties()));

        message.setContent(messageBody.getContent(), messageBody.getContentType());

        // Subject
        message.setSubject((String) model.get("subject"), "UTF-8");
        // From
        message.setFrom(new InternetAddress((String) model.get("from_email"), (String) model.get("from_name")));

        // TODO: to, cc and bcc headers come in a format not permited by rfc822
        // To comply with the rest of Tine we'll have to set those headers ourselves.

        // TO
        ArrayList<String> addressArray = (ArrayList<String>) model.get("to");
        for (String to : addressArray)
        {
            message.addRecipient(Message.RecipientType.TO, new InternetAddress(
                    to.substring(to.indexOf('<')+1, to.indexOf('>')-to.indexOf('<')+1)));
        }

        // CC
        addressArray = (ArrayList<String>) model.get("cc");
        for (String cc : addressArray)
        {
            message.addRecipient(Message.RecipientType.TO, new InternetAddress(
                    cc.substring(cc.indexOf('<')+1, cc.indexOf('>')-cc.indexOf('<')+1)));
        }

        // BCC
        addressArray = (ArrayList<String>) model.get("bcc");
        for (String bcc : addressArray)
        {
            message.addRecipient(Message.RecipientType.TO, new InternetAddress(
                    bcc.substring(bcc.indexOf('<')+1, bcc.indexOf('>')-bcc.indexOf('<')+1)));
        }
        
        // TODO: Notification
        // TODO: Importance

        return message;
    }
    
    public MimeMessage generateEncryptedMail(DigitalCertificate dc, Map messageModel){
        throw new NotImplementedException();
    }
    
    public static MimeMessage generateSignedMail(DigitalCertificate dc, Map<String, Object> messageModel, String userAgent)
            throws MessagingException, URISyntaxException, UnsupportedEncodingException, IOException,
            NoSuchAlgorithmException, KeyManagementException {
        
        // download attachments
        List<Map<String, Object>> attachments = (List<Map<String, Object>>) messageModel.get("attachments");
        List<Part> attachmentParts = SMIMEMailGenerator._getAttachments(attachments, userAgent);
        
        // get the bodyPart of the unsigned message body.
        MimeBodyPart unsignedBodyPart = SMIMEMailGenerator._buildUnsignedBodyPart((String) messageModel.get("body"),
            (String) messageModel.get("content_type"), attachmentParts);
                    
        // get the signed Multipart
        // MimeMultipart signedMail = dc.signMail(null);
        
        // Complete the message
        MimeMessage completeMessage = SMIMEMailGenerator._buildMimeMessage(unsignedBodyPart, messageModel);
        
        
        // todo: if attachments are empty, than we don't have a multipart mail.
        
        return null;
    }
    
}
