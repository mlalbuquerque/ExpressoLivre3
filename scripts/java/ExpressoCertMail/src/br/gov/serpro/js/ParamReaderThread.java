package br.gov.serpro.js;

import br.gov.serpro.mail.SMIMEMailGenerator;
import br.gov.serpro.setup.Setup;
import java.awt.Frame;
import java.io.IOException;
import java.io.UnsupportedEncodingException;
import java.net.URISyntaxException;
import java.security.KeyManagementException;
import java.security.NoSuchAlgorithmException;
import java.util.Map;
import java.util.logging.Level;
import java.util.logging.Logger;
import javax.activation.CommandMap;
import javax.activation.MailcapCommandMap;
import javax.mail.MessagingException;
import javax.mail.internet.MimeMessage;
import netscape.javascript.JSObject;
import org.codehaus.jackson.JsonFactory;
import org.codehaus.jackson.JsonParseException;
import org.codehaus.jackson.map.JsonMappingException;
import org.codehaus.jackson.map.ObjectMapper;

public class ParamReaderThread extends Thread {

	JSObject page;
	Javascript2AppletPassingData data;
	Setup setup;
	Frame parentFrame;
        
        static {
            // Define os tipos smime no mailcap
	    MailcapCommandMap mailcap = (MailcapCommandMap) CommandMap.getDefaultCommandMap();

	    mailcap.addMailcap("application/pkcs7-signature;; x-java-content-handler=org.bouncycastle.mail.smime.handlers.pkcs7_signature");
	    mailcap.addMailcap("application/pkcs7-mime;; x-java-content-handler=org.bouncycastle.mail.smime.handlers.pkcs7_mime");
	    mailcap.addMailcap("application/x-pkcs7-signature;; x-java-content-handler=org.bouncycastle.mail.smime.handlers.x_pkcs7_signature");
	    mailcap.addMailcap("application/x-pkcs7-mime;; x-java-content-handler=org.bouncycastle.mail.smime.handlers.x_pkcs7_mime");
	    mailcap.addMailcap("multipart/signed;; x-java-content-handler=org.bouncycastle.mail.smime.handlers.multipart_signed");

	    CommandMap.setDefaultCommandMap(mailcap);
        }
        

	public ParamReaderThread(JSObject page, Javascript2AppletPassingData data, Setup setup, Frame parent) {
	//public ParamReaderThread(JSObject page, Javascript2AppletPassingData data, Setup setup) {
		super();
		this.page = page;
		this.data = data;
		this.setup = setup;
		this.parentFrame = parent;
	}

	@Override
	public void run() {
            super.start();
            
            while (true){
                if (setup.getParameter("debug").equalsIgnoreCase("true")){
                        System.out.println("Classe ParamReaderThread: pegando resultado.");
                }
            
                try {
                    
                    Map<String, String> parsedData = data.getMap();
                    String userAgent = parsedData.get("operation");
                    System.out.println(parsedData.get("body"));
                    System.out.println("id: "+parsedData.get("ID"));
                    
                    ObjectMapper mapper = new ObjectMapper(new JsonFactory());
                    Map<String, Object> message = mapper.readValue(parsedData.get("body"), Map.class);
                    
                    MimeMessage completeMessage = SMIMEMailGenerator.generateSignedMail(null, message, userAgent);
                    
                    System.out.println("completeMessage:");
                    //System.out.println(new InputStreamReader(unsignedBodyPart.getRawInputStream(), "UTF-8").toString());
                    completeMessage.writeTo(System.out);
                    System.out.println();
                    
                    //page.call("Ext.get('"+ parsedData.get("ID") +"').fromApplet", new String[]{"json"});
                    page.call("appletStub", new String[]{parsedData.get("ID")});
                    
                } catch (URISyntaxException ex) {
                Logger.getLogger(ParamReaderThread.class.getName()).log(Level.SEVERE, null, ex);
                } catch (NoSuchAlgorithmException ex) {
                    Logger.getLogger(ParamReaderThread.class.getName()).log(Level.SEVERE, null, ex);
                } catch (KeyManagementException ex) {
                    Logger.getLogger(ParamReaderThread.class.getName()).log(Level.SEVERE, null, ex);
                } catch (MessagingException ex) {
                    Logger.getLogger(ParamReaderThread.class.getName()).log(Level.SEVERE, null, ex);
                } catch (UnsupportedEncodingException ex) {
                    Logger.getLogger(ParamReaderThread.class.getName()).log(Level.SEVERE, null, ex);
                } catch (JsonParseException ex) {
                    Logger.getLogger(ParamReaderThread.class.getName()).log(Level.SEVERE, null, ex);
                } catch (JsonMappingException ex) {
                    Logger.getLogger(ParamReaderThread.class.getName()).log(Level.SEVERE, null, ex);
                } catch (IOException ex) {
                    Logger.getLogger(ParamReaderThread.class.getName()).log(Level.SEVERE, null, ex);
                } catch (InterruptedException ex) {
                    Logger.getLogger(ParamReaderThread.class.getName()).log(Level.SEVERE, null, ex);
                }
                
                System.gc();
                //Thread.yield();

            }
	}
        
        
        
                
                
                // processa o smime. M�todo sign implementado na classe DigitalCertificate
//                String smime = null;
//                DigitalCertificate dc = null;
//                Map<String, String> parsedData = null;
//
//                try {
//
//                    //Map<String, String> parsedData = parseData(resultado);
//                    parsedData = data.getMap();
//
//                    dc = new DigitalCertificate(this.parentFrame, this.setup);
//                    dc.init();
//
//                    // Testa a opera��o e se for
//                    if (parsedData.get("operation").equals("sign")){
//
//                        smime = dc.signMail(parsedData);
//                        if (setup.getParameter("debug").equalsIgnoreCase("true")) {
//                            System.out.println("\nMensagem assinada: " + smime);
//                        }
//
//                    }
//                  else if (parsedData.get("operation").equals("decript")){
//                        String decryptedMsg = dc.cipherMail(parsedData);
//                        if (setup.getParameter("debug").equalsIgnoreCase("true")) {
//                            System.out.println("Mensagem decifrada: " + decryptedMsg);
//                        }
//                        if (decryptedMsg == null){
//                            smime = null;
//                        } else {
//                            smime = Base64Utils.base64Encode(decryptedMsg.getBytes());
//                        }
//
//                    }
//                    else {
//                        throw new UnsupportedOperationException("Operation not supported: " + parsedData.get("operation"));
//                        // Lan�a
//                    }
//
//                    // Retorna para a p�gina
//                    // se smime = null, a assinatura n�o funcionou
//
//                } catch (IOException e) {
//                    //DialogBuilder.showMessageDialog(this.parentFrame, "N�o foi poss�vel carregar Token/SmartCard: senha incorreta", this.setup);
//                    DialogBuilder.showMessageDialog(this.parentFrame, setup.getLang("ExpressoCertMailMessages", "ParamReaderThread001"), this.setup);
//                    //JOptionPane.showMessageDialog(this.parentFrame, "N�o foi poss�vel carregar Token/SmartCard: senha incorreta",
//                    //        "Aviso", JOptionPane.INFORMATION_MESSAGE);
//                    if (setup.getParameter("debug").equalsIgnoreCase("true")) {
//                        e.printStackTrace();
//                    }
//                } catch (GeneralSecurityException e) {
//                    if (e.getCause() != null){
//                        DialogBuilder.showMessageDialog(this.parentFrame, "GeneralSecurityException: " + e.getCause().getMessage(), this.setup);
//                        //JOptionPane.showMessageDialog(this.parentFrame, "GeneralSecurityException: " + e.getCause().getMessage(),
//                        //        "Aviso", JOptionPane.INFORMATION_MESSAGE);
//                    }
//                    else {
//                        DialogBuilder.showMessageDialog(this.parentFrame, "GeneralSecurityException: " + e.getMessage(), this.setup);
//                        //JOptionPane.showMessageDialog(this.parentFrame, "GeneralSecurityException: " + e.getMessage(),
//                        //        "Aviso", JOptionPane.INFORMATION_MESSAGE);
//                    }
//                    if (setup.getParameter("debug").equalsIgnoreCase("true")) {
//                        e.printStackTrace();
//                    }
//                } catch (SMIMEException e) {
//                    //DialogBuilder.showMessageDialog(this.parentFrame, "Erro no processamento da assinatura: " + e.getMessage(), this.setup);
//                    DialogBuilder.showMessageDialog(this.parentFrame, setup.getLang("ExpressoCertMailMessages", "ParamReaderThread002"), this.setup);
//                    //JOptionPane.showMessageDialog(this.parentFrame, "Erro no processamento da assinatura: " + e.getMessage(),
//                    //        "Aviso", JOptionPane.INFORMATION_MESSAGE);
//                    if (setup.getParameter("debug").equalsIgnoreCase("true")) {
//                        e.printStackTrace();
//                    }
//                } catch (MessagingException e) {
//                    //DialogBuilder.showMessageDialog(this.parentFrame, "Erro no processamento da mensagem: " + e.getMessage(), this.setup);
//                    DialogBuilder.showMessageDialog(this.parentFrame, setup.getLang("ExpressoCertMailMessages", "ParamReaderThread003"), this.setup);
//                    //JOptionPane.showMessageDialog(this.parentFrame, "Erro no processamento da mensagem: " + e.getMessage(),
//                    //        "Aviso", JOptionPane.INFORMATION_MESSAGE);
//                    if (setup.getParameter("debug").equalsIgnoreCase("true")) {
//                        e.printStackTrace();
//                    }
//                }
//                catch (CMSException e){
//                    //DialogBuilder.showMessageDialog(this.parentFrame, "Erro ao decifrar mensagem: Detectado problema na integridade da mensagem cifrada!", this.setup);
//                    DialogBuilder.showMessageDialog(this.parentFrame, setup.getLang("ExpressoCertMailMessages", "ParamReaderThread004"), this.setup);
//                    //if (setup.getParameter("debug").equalsIgnoreCase("true")) {
//                    Throwable cause = e.getCause();
//                    System.out.println(e.getClass().getCanonicalName() + ": " + e.getMessage());
//                    if (cause != null){
//                        System.out.println(cause.getClass().getCanonicalName() + ": " + cause.getMessage());
//                    }
//                    e.printStackTrace();
//                    //}
//                }
//                catch (ProviderException e){
//                    //DialogBuilder.showMessageDialog(this.parentFrame, "Problema no acesso �s informa��es do Token: " + e.getMessage(), this.setup);
//                    DialogBuilder.showMessageDialog(this.parentFrame, setup.getLang("ExpressoCertMailMessages", "ParamReaderThread005"), this.setup);
//                    //JOptionPane.showMessageDialog(this.parentFrame, "Problema no acesso �s informa��es do Token: " + e.getMessage(),
//                    //        "Aviso", JOptionPane.INFORMATION_MESSAGE);
//                    if (setup.getParameter("debug").equalsIgnoreCase("true")) {
//                        e.printStackTrace();
//                    }
//                }
//                catch (UnsupportedOperationException e){
//                    // DialogBuilder.showMessageDialog(this.parentFrame, e.getMessage());
//                    if (setup.getParameter("debug").equalsIgnoreCase("true")) {
//                        e.printStackTrace();
//                    }
//                }
//                catch (IllegalArgumentException e){
//                    //DialogBuilder.showMessageDialog(this.parentFrame, e.getMessage());
//                    if (setup.getParameter("debug").equalsIgnoreCase("true")) {
//                        e.printStackTrace();
//                    }
//                }
//                catch (InterruptedException e){
//                    if (setup.getParameter("debug").equalsIgnoreCase("true")){
//                        System.out.println("Classe ParamReaderThread: Thread has been interrupted! Break.");
//                        e.printStackTrace();
//                    }
//                    break;
//                }
//                finally {
//
//                   page.call("appletReturn", new String[]{smime, parsedData.get("ID"), parsedData.get("operation"), parsedData.get("folder")});
//
//                }
//
//                dc.destroy();
//                dc = null;

	//TODO: Documentar o que recebe!!!
	/*private Map<String, String> parseData(String expressoMailData){

        if ((expressoMailData == null) || (expressoMailData.length() == 0)){
            throw new IllegalArgumentException("Can't unserialize NULL or empty value!");
        }

		Map<String, String> parsedData = new HashMap<String, String> ();
		//Map<String, String> headersData = new HashMap<String, String>();

		for (String paramsArray : expressoMailData.split(";")){

			if (this.setup.getParameter("debug").equalsIgnoreCase("true")){
				System.out.println("sendo parseado: " + paramsArray);
			}
			String[] param = paramsArray.split(":");
			//if (temp[0].equals("headers")){
			//	String[] headersArray = new String(Base64Utils.base64Decode(temp[1])).split(";");
			//	for (String header: headersArray){
			//		String[] keyValueType = header.split(":");
			//		headersData.put(keyValueType[0], keyValueType[1]);
			//	}
			//}
			//else{
			parsedData.put(param[0], new String(Base64Utils.base64Decode(param[1])));
			//}

		}

		return parsedData;
	}

	static private String processReturn(Map smimeData){

		return new String();
	}
*/
}
