<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\RagAgent;

/**
 * Sample knowledge base with documentation for demo purposes.
 */
final class SampleKnowledgeBase
{
    /**
     * @return list<Document>
     */
    public static function getDocuments(): array
    {
        return [
            new Document(
                id: 'refund_policy',
                title: 'Refund Policy',
                source: 'policies/refund-policy.md',
                content: <<<'CONTENT'
                # Refund Policy

                ## Digital Products
                Digital products (software licenses, e-books, online courses) are non-refundable once the download link has been accessed or the content has been viewed. However, we offer a 30-day satisfaction guarantee: if you experience technical issues that prevent you from using the product, contact support for a full refund.

                ## Physical Products
                Physical products may be returned within 60 days of purchase for a full refund. Items must be unused and in original packaging. Shipping costs for returns are the customer's responsibility unless the item was defective.

                ## Subscription Services
                Subscription services can be cancelled at any time. Refunds for unused portions of annual subscriptions are prorated. Monthly subscriptions are not refunded but will not renew. Contact billing@example.com for subscription issues.

                ## How to Request a Refund
                1. Log into your account at account.example.com
                2. Navigate to Order History
                3. Click "Request Refund" next to the order
                4. Provide reason and any supporting information
                5. Wait 3-5 business days for review

                Refunds are processed to the original payment method within 7-10 business days after approval.
                CONTENT,
            ),

            new Document(
                id: 'api_authentication',
                title: 'API Authentication Guide',
                source: 'docs/api/authentication.md',
                content: <<<'CONTENT'
                # API Authentication

                ## Overview
                Our API uses Bearer token authentication. All requests must include an Authorization header with a valid API key.

                ## Getting an API Key
                1. Log into the Developer Portal at developers.example.com
                2. Navigate to API Keys section
                3. Click "Create New Key"
                4. Choose permissions: read-only or read-write
                5. Copy and securely store your key (it won't be shown again)

                ## Using Your API Key
                Include the key in every request header:
                Authorization: Bearer your-api-key-here

                ## Rate Limits
                - Free tier: 100 requests per minute
                - Pro tier: 1000 requests per minute
                - Enterprise: Custom limits

                Rate limit headers are included in every response:
                - X-RateLimit-Limit: Maximum requests allowed
                - X-RateLimit-Remaining: Requests remaining in window
                - X-RateLimit-Reset: Unix timestamp when limit resets

                ## Security Best Practices
                - Never expose API keys in client-side code
                - Rotate keys every 90 days
                - Use environment variables for key storage
                - Monitor usage for unusual patterns
                - Immediately revoke compromised keys
                CONTENT,
            ),

            new Document(
                id: 'shipping_info',
                title: 'Shipping Information',
                source: 'help/shipping.md',
                content: <<<'CONTENT'
                # Shipping Information

                ## Domestic Shipping (United States)
                - Standard (5-7 business days): Free on orders over $50, otherwise $5.99
                - Express (2-3 business days): $12.99
                - Next Day: $24.99 (order by 2pm EST)

                ## International Shipping
                - Canada and Mexico: $15.99 (7-14 business days)
                - Europe: $24.99 (10-21 business days)
                - Rest of World: $34.99 (14-30 business days)

                International orders may be subject to customs duties and taxes, which are the customer's responsibility.

                ## Tracking Your Order
                Tracking numbers are emailed within 24 hours of shipment. Track at track.example.com or use the carrier's website directly.

                ## Shipping Restrictions
                We cannot ship to P.O. boxes for orders containing lithium batteries. Some products have country-specific restrictions due to regulations.

                ## Lost or Damaged Packages
                Contact support within 14 days of expected delivery date. We will file a claim with the carrier and either reship or refund your order.
                CONTENT,
            ),

            new Document(
                id: 'privacy_policy',
                title: 'Privacy Policy',
                source: 'legal/privacy.md',
                content: <<<'CONTENT'
                # Privacy Policy

                Last updated: January 2026

                ## Data We Collect
                - Account information: name, email, password hash
                - Payment data: processed by Stripe, we don't store card numbers
                - Usage data: pages visited, features used, error logs
                - Device information: browser type, OS, IP address

                ## How We Use Your Data
                - Provide and improve our services
                - Process transactions
                - Send service communications
                - Detect fraud and abuse
                - Comply with legal obligations

                ## Data Retention
                - Account data: kept while account is active
                - Transaction records: 7 years for tax purposes
                - Usage logs: 90 days
                - Support tickets: 3 years

                ## Your Rights (GDPR/CCPA)
                - Access your data
                - Correct inaccuracies
                - Delete your account
                - Export your data
                - Opt out of marketing

                Contact privacy@example.com to exercise these rights.

                ## Cookies
                We use essential cookies for authentication and preferences. Analytics cookies are optional and can be disabled in settings.
                CONTENT,
            ),
        ];
    }
}
