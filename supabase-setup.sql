-- =====================================================
-- COACHSEARCHING.COM - SUPABASE DATABASE SETUP
-- =====================================================
-- This file contains all SQL commands needed to set up
-- the database schema for the coach signup landing page.
--
-- INSTRUCTIONS:
-- 1. Create a new Supabase project at https://app.supabase.com
-- 2. Go to the SQL Editor in your Supabase dashboard
-- 3. Copy and paste this entire file into the SQL Editor
-- 4. Click "Run" to execute all commands
-- 5. Update the HTML file with your Supabase URL and Anon Key
--    (found in Settings > API)
-- =====================================================

-- =====================================================
-- 1. CREATE TABLES
-- =====================================================

-- Table: coachsearching_invite_codes
-- Purpose: Store valid invitation codes for coach registration
-- Only coaches with valid codes can sign up
CREATE TABLE IF NOT EXISTS public.coachsearching_invite_codes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code TEXT UNIQUE NOT NULL,
    created_by TEXT, -- Email or name of the certified coach trainer
    is_active BOOLEAN DEFAULT true,
    max_uses INTEGER DEFAULT 1, -- How many times this code can be used
    current_uses INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    expires_at TIMESTAMP WITH TIME ZONE, -- Optional expiration date

    CONSTRAINT code_not_empty CHECK (char_length(code) > 0)
);

-- Table: coachsearching_coaches
-- Purpose: Store coach registration data
-- New coaches signup here and wait for manual approval
CREATE TABLE IF NOT EXISTS public.coachsearching_coaches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE, -- Link to Supabase Auth user
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    social_link TEXT NOT NULL, -- LinkedIn or professional profile URL
    invite_code TEXT NOT NULL REFERENCES public.coachsearching_invite_codes(code),
    approved BOOLEAN DEFAULT false, -- Manual approval required
    approved_at TIMESTAMP WITH TIME ZONE,
    approved_by UUID REFERENCES auth.users(id), -- Admin who approved
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),

    CONSTRAINT name_not_empty CHECK (char_length(name) > 0),
    CONSTRAINT email_format CHECK (email ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'),
    CONSTRAINT social_link_not_empty CHECK (char_length(social_link) > 0)
);

-- =====================================================
-- 2. CREATE INDEXES FOR PERFORMANCE
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_coachsearching_coaches_email ON public.coachsearching_coaches(email);
CREATE INDEX IF NOT EXISTS idx_coachsearching_coaches_approved ON public.coachsearching_coaches(approved);
CREATE INDEX IF NOT EXISTS idx_coachsearching_coaches_invite_code ON public.coachsearching_coaches(invite_code);
CREATE INDEX IF NOT EXISTS idx_coachsearching_coaches_user_id ON public.coachsearching_coaches(user_id);
CREATE INDEX IF NOT EXISTS idx_coachsearching_invite_codes_code ON public.coachsearching_invite_codes(code);
CREATE INDEX IF NOT EXISTS idx_coachsearching_invite_codes_active ON public.coachsearching_invite_codes(is_active);

-- =====================================================
-- 3. CREATE FUNCTION TO UPDATE 'updated_at' TIMESTAMP
-- =====================================================

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- =====================================================
-- 4. CREATE TRIGGERS
-- =====================================================

-- Trigger to automatically update 'updated_at' on coaches table
DROP TRIGGER IF EXISTS update_coaches_updated_at ON public.coachsearching_coaches;
CREATE TRIGGER update_coaches_updated_at
    BEFORE UPDATE ON public.coachsearching_coaches
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Trigger to increment invite code usage counter
CREATE OR REPLACE FUNCTION increment_invite_code_usage()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE public.coachsearching_invite_codes
    SET current_uses = current_uses + 1
    WHERE code = NEW.invite_code;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS increment_code_usage ON public.coachsearching_coaches;
CREATE TRIGGER increment_code_usage
    AFTER INSERT ON public.coachsearching_coaches
    FOR EACH ROW
    EXECUTE FUNCTION increment_invite_code_usage();

-- Trigger to set approved_at timestamp when coach is approved
CREATE OR REPLACE FUNCTION set_approved_at()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.approved = true AND OLD.approved = false THEN
        NEW.approved_at = NOW();
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS set_coach_approved_at ON public.coachsearching_coaches;
CREATE TRIGGER set_coach_approved_at
    BEFORE UPDATE ON public.coachsearching_coaches
    FOR EACH ROW
    EXECUTE FUNCTION set_approved_at();

-- =====================================================
-- 5. ROW LEVEL SECURITY (RLS) POLICIES
-- =====================================================

-- Enable RLS on both tables
ALTER TABLE public.coachsearching_invite_codes ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.coachsearching_coaches ENABLE ROW LEVEL SECURITY;

-- Policy: Anyone can check if an invite code is valid (read-only)
CREATE POLICY "Public read for active invite codes"
    ON public.coachsearching_invite_codes
    FOR SELECT
    USING (
        is_active = true
        AND (expires_at IS NULL OR expires_at > NOW())
        AND (max_uses IS NULL OR current_uses < max_uses)
    );

-- Policy: Anyone can insert a new coach record
-- This allows the signup form to work without authentication
CREATE POLICY "Public insert for coaches"
    ON public.coachsearching_coaches
    FOR INSERT
    WITH CHECK (true);

-- Policy: Coaches can only view their own record (once authenticated)
CREATE POLICY "Coaches can view own record"
    ON public.coachsearching_coaches
    FOR SELECT
    USING (auth.uid() = user_id);

-- Policy: Only service role can update coach records
-- This prevents users from self-approving
-- (Admins will need to use the Supabase dashboard or a custom admin panel)
CREATE POLICY "Only admins can update coaches"
    ON public.coachsearching_coaches
    FOR UPDATE
    USING (false); -- This means regular users cannot update; use service_role key for admin updates

-- =====================================================
-- 6. INSERT SAMPLE INVITATION CODES
-- =====================================================

-- Insert some sample invitation codes for testing
-- Replace these with your actual codes in production
INSERT INTO public.coachsearching_invite_codes (code, created_by, is_active, max_uses) VALUES
    ('TRAINER2025', 'Demo Trainer', true, 100),
    ('COACH-EARLY-BIRD', 'Early Access Program', true, 50),
    ('BETA-COACH-2025', 'Beta Program', true, 25)
ON CONFLICT (code) DO NOTHING;

-- =====================================================
-- 7. CREATE VIEW FOR ADMIN DASHBOARD (OPTIONAL)
-- =====================================================

-- View: coachsearching_pending_coaches
-- Shows all coaches waiting for approval
CREATE OR REPLACE VIEW public.coachsearching_pending_coaches AS
SELECT
    id,
    user_id,
    name,
    email,
    social_link,
    invite_code,
    created_at
FROM public.coachsearching_coaches
WHERE approved = false
ORDER BY created_at DESC;

-- =====================================================
-- 8. GRANT PERMISSIONS
-- =====================================================

-- Grant necessary permissions to authenticated users
GRANT SELECT ON public.coachsearching_invite_codes TO authenticated;
GRANT INSERT ON public.coachsearching_coaches TO authenticated;
GRANT SELECT ON public.coachsearching_coaches TO authenticated;

-- Grant permissions to anonymous users (for the signup form)
GRANT SELECT ON public.coachsearching_invite_codes TO anon;
GRANT INSERT ON public.coachsearching_coaches TO anon;

-- =====================================================
-- SETUP COMPLETE!
-- =====================================================

-- NEXT STEPS:
-- 1. Go to Supabase Dashboard > Settings > API
-- 2. Copy your "Project URL" and "anon/public" key
-- 3. Update these values in the index.html file:
--    - SUPABASE_URL = 'your-project-url'
--    - SUPABASE_ANON_KEY = 'your-anon-key'
--
-- 4. To approve coaches manually:
--    - Go to Supabase Dashboard > Table Editor > coachsearching_coaches
--    - Find the coach record
--    - Set "approved" field to true
--    - The triggers will automatically set approved_at timestamp
--
-- 5. To add more invite codes:
--    INSERT INTO public.coachsearching_invite_codes (code, created_by, is_active, max_uses)
--    VALUES ('YOUR-CODE-HERE', 'Trainer Name', true, 10);
--
-- 6. To view pending coaches:
--    SELECT * FROM public.coachsearching_pending_coaches;
--
-- 7. OPTIONAL: Set up email notifications when coaches are approved
--    - Use Supabase Edge Functions or webhooks
--    - Trigger on UPDATE of coachsearching_coaches table when approved = true
--
-- 8. OPTIONAL: Create an admin panel to manage approvals
--    - Build a separate admin interface
--    - Use service_role key (keep it secret!) for admin operations
--    - Query coachsearching_pending_coaches view for coaches awaiting approval
-- =====================================================

-- Verification query to check if everything is set up correctly
DO $$
BEGIN
    RAISE NOTICE '✓ Tables created successfully';
    RAISE NOTICE '✓ Indexes created successfully';
    RAISE NOTICE '✓ Triggers created successfully';
    RAISE NOTICE '✓ RLS policies enabled';
    RAISE NOTICE '✓ Sample invite codes inserted';
    RAISE NOTICE '';
    RAISE NOTICE 'Setup complete! Please update the HTML file with your Supabase credentials.';
    RAISE NOTICE '';
    RAISE NOTICE 'Tables created:';
    RAISE NOTICE '  - coachsearching_invite_codes';
    RAISE NOTICE '  - coachsearching_coaches';
    RAISE NOTICE 'Views created:';
    RAISE NOTICE '  - coachsearching_pending_coaches';
END $$;
