/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

package br.gov.serpro.cert;

import br.gov.serpro.setup.Setup;
import java.io.ByteArrayInputStream;
import java.io.File;
import java.io.IOException;
import java.security.Provider;
import java.security.ProviderException;
import java.security.Security;

//TODO: Deal with wildcards for environments variables.

/**
 *
 * @author esa
 */
class Token{

    private final Setup setup;
    private String name;
    private String libraryPath;
    private Provider tokenProvider;
    private boolean registered = false;
    private long slot;

    static long CK_OBJECT_CLASS;
    static long CK_OBJECT_HANDLE;

    private Token(final Setup setup) {
        this.setup = setup;
    }

    Token(String name, String libraryPath, final Setup setup){
        this(setup);
        this.setName(name);
        this.setLibraryPath(libraryPath);
    }

    public boolean isRegistered() {
        return this.registered;
    }

    public void setLibraryPath(String libraryPath) {
        this.libraryPath = libraryPath;
    }

    public void setName(String name) {
        this.name = name;
    }

    public String getName() {
        return this.name;
    }

    public String getProviderName(){
        return this.tokenProvider.getName();
    }

    protected void registerToken(long slot) throws IOException{

        this.slot = slot;
        String tokenConfiguration = new String("name = " + name + "_" + slot + "\n" +
            "library = " + libraryPath + "\nslot = " + slot +
            "\ndisabledMechanisms = {\n" + "CKM_SHA1_RSA_PKCS\n}" +
            "\n");

        try{
            this.registered = false;
            if (libraryExists()){
                Provider pkcs11Provider = new sun.security.pkcs11.SunPKCS11(new ByteArrayInputStream(tokenConfiguration.getBytes()));
                this.tokenProvider = pkcs11Provider;
                if (setup.getParameter("debug").equalsIgnoreCase("true")) {
                    System.out.println("Adding provider: "+pkcs11Provider.getName());
                    System.out.println("Provider info: " + pkcs11Provider.getInfo());
                }
                Security.addProvider(pkcs11Provider);
                this.setName(this.tokenProvider.getName());
                this.registered = true;
            }
        }
        catch (ProviderException e){
            if (setup.getParameter("debug").equalsIgnoreCase("true")) {
                e.printStackTrace();
                System.out.println("N�o foi poss�vel inicializar o seguinte token: " + tokenConfiguration);
            }
        }
    }

    protected void unregisterToken(){
        Security.removeProvider(this.tokenProvider.getName());
        this.registered = false;
    }
    
    public boolean libraryExists(){

        File libraryFile = new File(libraryPath);
        if (libraryFile.exists()){
            if (setup.getParameter("debug").equalsIgnoreCase("true")) {
                System.out.println("Arquivo " + libraryPath + " existe.");
            }
            return true;
        }
        
        if (setup.getParameter("debug").equalsIgnoreCase("true")) {
            System.out.println("Biblioteca do Token/SmartCard " + name + " n�o foi encontrada: " + libraryPath);
        }
        
        return false;
    }
    
}
