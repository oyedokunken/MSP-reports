import { Link } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';

export default function PrivacyPolicy() {
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
            Privacy Policy
          </h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mb-8">
            Last updated: {new Date().toLocaleDateString('en-AU', { year: 'numeric', month: 'long', day: 'numeric' })}
          </p>

          <div className="prose prose-indigo dark:prose-invert max-w-none">
            <h2>1. Introduction</h2>
            <p>
              {companyName} ("we", "our", or "us") is committed to protecting your privacy. 
              This Privacy Policy explains how we collect, use, disclose, and safeguard your 
              information when you use our reporting platform.
            </p>

            <h2>2. Information We Collect</h2>
            <h3>2.1 Personal Information</h3>
            <p>We may collect personal information that you provide directly to us, including:</p>
            <ul>
              <li>Name and email address</li>
              <li>Company name and contact details</li>
              <li>Login credentials</li>
              <li>Any other information you choose to provide</li>
            </ul>

            <h3>2.2 Usage Information</h3>
            <p>We automatically collect certain information when you use our platform:</p>
            <ul>
              <li>IP address and browser type</li>
              <li>Device information</li>
              <li>Pages visited and features used</li>
              <li>Date and time of access</li>
            </ul>

            <h3>2.3 Integration Data</h3>
            <p>
              When you connect third-party integrations, we may access and store data from 
              those services as necessary to provide our reporting functionality.
            </p>

            <h2>3. How We Use Your Information</h2>
            <p>We use the information we collect to:</p>
            <ul>
              <li>Provide, maintain, and improve our services</li>
              <li>Generate reports and analytics</li>
              <li>Send you technical notices and support messages</li>
              <li>Respond to your comments and questions</li>
              <li>Protect against fraudulent or illegal activity</li>
              <li>Comply with legal obligations</li>
            </ul>

            <h2>4. Data Security</h2>
            <p>
              We implement appropriate technical and organizational measures to protect your 
              personal information, including:
            </p>
            <ul>
              <li>Encryption of data in transit and at rest</li>
              <li>Regular security assessments</li>
              <li>Access controls and authentication</li>
              <li>Audit logging of all system access</li>
            </ul>

            <h2>5. Data Retention</h2>
            <p>
              We retain your personal information for as long as necessary to provide our 
              services and fulfill the purposes described in this policy. Retention periods 
              may vary based on legal requirements and business needs.
            </p>

            <h2>6. Your Rights</h2>
            <p>Depending on your location, you may have the right to:</p>
            <ul>
              <li>Access your personal information</li>
              <li>Correct inaccurate data</li>
              <li>Request deletion of your data</li>
              <li>Object to or restrict processing</li>
              <li>Data portability</li>
            </ul>

            <h2>7. Third-Party Services</h2>
            <p>
              Our platform may integrate with third-party services. We are not responsible 
              for the privacy practices of these services. We encourage you to review their 
              privacy policies.
            </p>

            <h2>8. Changes to This Policy</h2>
            <p>
              We may update this Privacy Policy from time to time. We will notify you of 
              any changes by posting the new policy on this page and updating the "Last 
              updated" date.
            </p>

            <h2>9. Contact Us</h2>
            <p>
              If you have any questions about this Privacy Policy, please contact us at:
            </p>
            <p>
              <strong>Email:</strong> privacy@{companyName.toLowerCase().replace(/\s+/g, '')}.com.au
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
