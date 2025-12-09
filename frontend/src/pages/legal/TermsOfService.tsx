import { Link } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';

export default function TermsOfService() {
  const companyName = import.meta.env.VITE_COMPANY_NAME || 'MSP Reports';
  
  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <div className="max-w-4xl mx-auto px-4 py-12">
        {/* Back to Login */}
        <Link 
          to="/login" 
          className="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-500 mb-8"
        >
          <ArrowLeft className="w-4 h-4 mr-2" />
          Back to Login
        </Link>

        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8 md:p-12">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
            Terms of Service
          </h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mb-8">
            Last updated: {new Date().toLocaleDateString('en-AU', { year: 'numeric', month: 'long', day: 'numeric' })}
          </p>

          <div className="space-y-6">
            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">1. Acceptance of Terms</h2>
              <p className="text-gray-600 dark:text-gray-300 leading-relaxed">
                By accessing or using the {companyName} platform ("Service"), you agree to be 
                bound by these Terms of Service ("Terms"). If you do not agree to these Terms, 
                you may not access or use the Service.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">2. Description of Service</h2>
              <p className="text-gray-600 dark:text-gray-300 leading-relaxed">
                {companyName} provides a multi-tenant reporting platform that consolidates data 
                from multiple business systems, maps customers across integrations, and generates 
                automated reports. The Service is provided on an "as is" and "as available" basis.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">3. User Accounts</h2>
              
              <h3 className="text-lg font-medium text-gray-800 dark:text-gray-200 mb-2">3.1 Account Creation</h3>
              <p className="text-gray-600 dark:text-gray-300 mb-4">
                To use the Service, you must create an account with accurate and complete 
                information. You are responsible for maintaining the confidentiality of your 
                account credentials.
              </p>

              <h3 className="text-lg font-medium text-gray-800 dark:text-gray-200 mb-2">3.2 Account Security</h3>
              <p className="text-gray-600 dark:text-gray-300">
                You are responsible for all activities that occur under your account. You must 
                notify us immediately of any unauthorized use of your account or any other 
                breach of security.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">4. Acceptable Use</h2>
              <p className="text-gray-600 dark:text-gray-300 mb-2">You agree not to:</p>
              <ul className="list-disc list-inside text-gray-600 dark:text-gray-300 space-y-1 ml-4">
                <li>Use the Service for any unlawful purpose</li>
                <li>Attempt to gain unauthorized access to any part of the Service</li>
                <li>Interfere with or disrupt the Service or servers</li>
                <li>Upload malicious code or content</li>
                <li>Violate any applicable laws or regulations</li>
                <li>Infringe on the intellectual property rights of others</li>
              </ul>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">5. Data and Privacy</h2>
              <p className="text-gray-600 dark:text-gray-300">
                Your use of the Service is also governed by our{' '}
                <a href="/privacy-policy" className="text-indigo-600 hover:text-indigo-500 underline">Privacy Policy</a>. 
                By using the Service, you consent to the collection and use of information as described in 
                the Privacy Policy.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">6. Intellectual Property</h2>
              <p className="text-gray-600 dark:text-gray-300">
                The Service and its original content, features, and functionality are owned by {' '}
                {companyName} and are protected by international copyright, trademark, patent, 
                trade secret, and other intellectual property laws.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">7. Third-Party Integrations</h2>
              <p className="text-gray-600 dark:text-gray-300">
                The Service may integrate with third-party services. Your use of such 
                integrations is subject to the terms and conditions of those third parties. 
                We are not responsible for the content or practices of third-party services.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">8. Limitation of Liability</h2>
              <p className="text-gray-600 dark:text-gray-300">
                To the maximum extent permitted by law, {companyName} shall not be liable for 
                any indirect, incidental, special, consequential, or punitive damages, or any 
                loss of profits or revenues, whether incurred directly or indirectly.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">9. Disclaimer of Warranties</h2>
              <p className="text-gray-600 dark:text-gray-300">
                The Service is provided "as is" without warranties of any kind, either express 
                or implied, including but not limited to implied warranties of merchantability, 
                fitness for a particular purpose, and non-infringement.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">10. Indemnification</h2>
              <p className="text-gray-600 dark:text-gray-300">
                You agree to indemnify and hold harmless {companyName} and its officers, 
                directors, employees, and agents from any claims, damages, losses, liabilities, 
                and expenses arising out of your use of the Service or violation of these Terms.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">11. Termination</h2>
              <p className="text-gray-600 dark:text-gray-300">
                We may terminate or suspend your account and access to the Service immediately, 
                without prior notice, for conduct that we believe violates these Terms or is 
                harmful to other users, us, or third parties.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">12. Changes to Terms</h2>
              <p className="text-gray-600 dark:text-gray-300">
                We reserve the right to modify these Terms at any time. We will provide notice 
                of significant changes by posting the new Terms on this page. Your continued 
                use of the Service after such changes constitutes acceptance of the new Terms.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">13. Governing Law</h2>
              <p className="text-gray-600 dark:text-gray-300">
                These Terms shall be governed by and construed in accordance with the laws of 
                Australia, without regard to its conflict of law provisions.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-3">14. Contact Us</h2>
              <p className="text-gray-600 dark:text-gray-300 mb-2">
                If you have any questions about these Terms, please contact us at:
              </p>
              <p className="text-gray-600 dark:text-gray-300">
                <strong className="text-gray-900 dark:text-white">Email:</strong>{' '}
                <a href={`mailto:legal@${companyName.toLowerCase().replace(/\s+/g, '')}.com.au`} className="text-indigo-600 hover:text-indigo-500 underline">
                  legal@{companyName.toLowerCase().replace(/\s+/g, '')}.com.au
                </a>
              </p>
            </section>
          </div>
        </div>
      </div>
    </div>
  );
}
